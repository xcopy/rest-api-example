<?php

declare(strict_types=1);

namespace App\Services;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailVerificationService
{
    private Sql $sql;

    /** @var array{dsn: string, from_address: string, from_name: string, app_url: string, verification_ttl: string} */
    private array $config;

    public function __construct(Adapter $db, private MailerInterface $mailer)
    {
        $this->sql = new Sql($db);
        $this->config = require APP_BASE_PATH . '/config/mail.php';
    }

    public function sendVerification(int $user_id, string $email, ?string &$token = null): void
    {
        $this->sql
            ->prepareStatementForSqlObject(
                $this->sql->delete('email_verification_tokens')->where(['user_id' => $user_id])
            )
            ->execute();

        $this->sql
            ->prepareStatementForSqlObject(
                $this->sql->delete('user_tokens')->where(['user_id' => $user_id])
            )
            ->execute();

        $token = TokenService::generateToken();
        $expiresAt = DateTimeService::now()->modify($this->config['verification_ttl']);

        $insert = $this->sql
            ->insert('email_verification_tokens')
            ->values([
                'user_id' => $user_id,
                'token_hash' => TokenService::hash($token),
                'expires_at' => $expiresAt->format(DateTimeService::FORMAT),
            ]);

        $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();

        $verificationUrl = rtrim($this->config['app_url'], '/') . '/auth/verify-email';

        $message = (new Email())
            ->from(new Address($this->config['from_address'], $this->config['from_name']))
            ->to($email)
            ->subject('Verify your email address')
            ->text(sprintf(
                "Hello,\n\nTo verify your email address, send a POST request to:\n%s\n\n"
                    . "with this JSON body:\n{\"token\":\"%s\"}\n\n"
                    . "This token expires at %s.\n",
                $verificationUrl,
                $token,
                $expiresAt->format(DATE_ATOM)
            ));

        $this->mailer->send($message);
    }

    public function verify(string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return false;
        }

        $now = DateTimeService::now(true);
        $predicate = [
            'token_hash' => TokenService::hash($token),
            'expires_at > ?' => $now,
        ];

        $select = $this->sql
            ->select('email_verification_tokens')
            ->columns(['user_id'])
            ->where($predicate)
            ->limit(1);

        $verificationToken = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        if ($verificationToken === false) {
            return false;
        }

        $consumePredicate = [
            'token_hash' => TokenService::hash($token),
            'expires_at > ?' => $now,
        ];
        $consume = $this->sql
            ->delete('email_verification_tokens')
            ->where($consumePredicate);

        $consumed = $this->sql
            ->prepareStatementForSqlObject($consume)
            ->execute();

        if ($consumed->getAffectedRows() !== 1) {
            return false;
        }

        $user_id = (int) $verificationToken['user_id'];

        $update = $this->sql
            ->update('users')
            ->set(['email_verified_at' => $now])
            ->where(['id' => $user_id]);

        $this->sql
            ->prepareStatementForSqlObject($update)
            ->execute();

        return true;
    }
}
