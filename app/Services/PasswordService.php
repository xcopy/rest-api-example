<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DI\Attribute\Inject;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;

class PasswordService
{
    #[Inject]
    protected Adapter $db;

    #[Inject]
    protected Sql $sql;

    #[Inject]
    protected ConfigService $config;

    #[Inject]
    protected EmailService $emailService;

    public static function generateHash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function sendReset(int $user_id, string $email, ?string &$token = null): void
    {
        $delete = $this->sql
            ->delete('password_reset_tokens')
            ->where(compact('user_id'));

        $this->sql
            ->prepareStatementForSqlObject($delete)
            ->execute();

        $token = generateToken();
        $expiresAt = now()->modify($this->config->get('password.reset_ttl'));

        $insert = $this->sql
            ->insert('password_reset_tokens')
            ->values([
                'user_id' => $user_id,
                'token_hash' => hashToken($token),
                'expires_at' => $expiresAt->format(DATE_FORMAT_DB),
            ]);

        $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();

        $resetUrl = rtrim($this->config->get('app.base_url'), '/') . '/auth/reset-password';

        $message = $this->emailService
            ->compose()
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

        $this->emailService->send($message);
    }

    public function reset(string $token, string $password): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return false;
        }

        $now = now(true);
        $predicate = [
            'token_hash' => hashToken($token),
            'expires_at > ?' => $now,
        ];

        $select = $this->sql
            ->select('password_reset_tokens')
            ->columns(['user_id'])
            ->where($predicate)
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
                    'token_hash' => hashToken($token),
                    'expires_at > ?' => $now,
                ]);

            $consumed = $this->sql
                ->prepareStatementForSqlObject($consume)
                ->execute();

            if ($consumed->getAffectedRows() !== 1) {
                $connection->rollback();

                return false;
            }

            if (!$this->change($user_id, $password)) {
                $connection->rollback();

                return false;
            }

            $connection->commit();

            return true;
        } catch (\Throwable $exception) {
            $connection->rollback();

            throw $exception;
        }
    }

    public function change(
        int $user_id,
        string $password,
        ?DateTimeImmutable $changedBefore = null
    ): bool {
        $now = now(true);
        $update = $this->sql
            ->update('users')
            ->set([
                'password' => self::generateHash($password),
                'password_changed_at' => $now,
                'updated_at' => $now,
            ])
            ->where(['id' => $user_id]);

        if ($changedBefore !== null) {
            $update->where([
                '(password_changed_at IS NULL OR password_changed_at <= ?)' => $changedBefore->format(DATE_FORMAT_DB),
            ]);
        }

        $result = $this->sql
            ->prepareStatementForSqlObject($update)
            ->execute();

        if ($result->getAffectedRows() !== 1) {
            return false;
        }

        $revokeTokens = $this->sql
            ->delete('user_tokens')
            ->where(['user_id' => $user_id]);

        $this->sql
            ->prepareStatementForSqlObject($revokeTokens)
            ->execute();

        return true;
    }
}
