<?php

declare(strict_types=1);

namespace App\Services;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class PasswordResetService
{
    private Sql $sql;

    /** @var array{dsn: string, from_address: string, from_name: string, app_url: string, verification_ttl: string, password_reset_ttl: string} */
    private array $config;

    public function __construct(
        private Adapter $db,
        private MailerInterface $mailer
    ) {
        $this->sql = new Sql($db);
        $this->config = require APP_BASE_PATH . '/config/mail.php';
    }

    public function sendReset(int $user_id, string $email, ?string &$token = null): void
    {
        $this->sql
            ->prepareStatementForSqlObject(
                $this->sql->delete('password_reset_tokens')->where(['user_id' => $user_id])
            )
            ->execute();

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify($this->config['password_reset_ttl']);

        $insert = $this->sql
            ->insert('password_reset_tokens')
            ->values([
                'user_id' => $user_id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

        $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();

        $resetUrl = rtrim($this->config['app_url'], '/') . '/auth/reset-password';

        $message = (new Email())
            ->from(new Address($this->config['from_address'], $this->config['from_name']))
            ->to($email)
            ->subject('Reset your password')
            ->text(sprintf(
                "Hello,\n\nTo reset your password, send a POST request to:\n%s\n\n"
                    . "with this JSON body:\n{\"token\":\"%s\",\"new_password\":\"<new password>\","
                    . "\"new_password_repeat\":\"<new password>\"}\n\n"
                    . "This token expires at %s.\n",
                $resetUrl,
                $token,
                $expiresAt->format(DATE_ATOM)
            ));

        $this->mailer->send($message);
    }

    public function resetPassword(string $token, string $password): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return false;
        }

        $tokenHash = hash('sha256', $token);
        $now = gmdate('Y-m-d H:i:s');
        $select = $this->sql
            ->select('password_reset_tokens')
            ->columns(['user_id'])
            ->where([
                'token_hash' => $tokenHash,
                'expires_at > ?' => $now,
            ])
            ->limit(1);

        $resetToken = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        if ($resetToken === false) {
            return false;
        }

        $user_id = (int) $resetToken['user_id'];
        $connection = $this->db->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            $consume = $this->sql
                ->delete('password_reset_tokens')
                ->where([
                    'token_hash' => $tokenHash,
                    'expires_at > ?' => gmdate('Y-m-d H:i:s'),
                ]);

            $consumed = $this->sql
                ->prepareStatementForSqlObject($consume)
                ->execute();

            if ($consumed->getAffectedRows() !== 1) {
                $connection->rollback();

                return false;
            }

            $now = gmdate('Y-m-d H:i:s');
            $update = $this->sql
                ->update('users')
                ->set([
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'password_changed_at' => $now,
                    'updated_at' => $now,
                ])
                ->where(['id' => $user_id]);

            $this->sql
                ->prepareStatementForSqlObject($update)
                ->execute();

            $revokeTokens = $this->sql
                ->delete('user_tokens')
                ->where(['user_id' => $user_id]);

            $this->sql
                ->prepareStatementForSqlObject($revokeTokens)
                ->execute();

            $connection->commit();

            return true;
        } catch (\Throwable $exception) {
            $connection->rollback();

            throw $exception;
        }
    }
}
