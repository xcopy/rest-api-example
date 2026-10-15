<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\HasFactory;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Repositories\UserRepository;
use App\Services\Concerns\ValidatesData;
use App\Validators\PasswordValidator;
use DateInterval;
use DateTimeImmutable;
use DI\Attribute\Inject;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Slim\Interfaces\RouteParserInterface;
use Throwable;

class PasswordService
{
    use HasFactory;
    use ValidatesData;

    #[Inject]
    protected Adapter $db;

    #[Inject]
    protected Sql $sql;

    #[Inject]
    protected ConfigService $config;

    #[Inject]
    protected EmailService $email;

    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected RouteParserInterface $routeParser;

    protected function validatorClass(): string
    {
        return PasswordValidator::class;
    }

    /** @param array<string, mixed> $data */
    public function sendReset(array $data, ?string &$token = null): void
    {
        $data = $this->validate($data, 'forgot');

        $email = $data['email'];

        $user = $this->userRepository->findOne(compact('email'));

        if ($user === null) {
            throw new NotFoundException();
        }

        $token = generateToken();
        $expiresAt = now()->modify($this->config->get('password.reset_ttl'));

        $connection = $this->db->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            $delete = $this->sql
                ->delete('password_reset_tokens')
                ->where(['user_id' => $user['id']]);

            $this->sql
                ->prepareStatementForSqlObject($delete)
                ->execute();

            $insert = $this->sql
                ->insert('password_reset_tokens')
                ->values([
                    'user_id' => $user['id'],
                    'token_hash' => hashToken($token),
                    'expires_at' => $expiresAt->format(DATE_FORMAT_DB),
                ]);

            $this->sql
                ->prepareStatementForSqlObject($insert)
                ->execute();

            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollback();
            throw $e;
        }

        $url = rtrim($this->config->get('app.base_url'), '/') . $this->routeParser->urlFor('password.reset');

        $message = $this->email
            ->compose()
            ->to($email)
            ->subject('Reset your password')
            ->text(sprintf(
                "Hello,\n\nTo reset your password, send a POST request to:\n%s\n\n"
                    . "with this JSON body:\n{\"token\":\"%s\",\"new_password\":\"<new password>\","
                    . "\"new_password_repeat\":\"<new password>\"}\n\n"
                    . "This token expires at %s.\n",
                $url,
                $token,
                $expiresAt->format(DATE_ATOM)
            ));

        $this->email->send($message);
    }

    /** @param array<string, mixed> $data */
    public function reset(array $data): bool
    {
        $data = $this->validate($data, 'reset');

        $where = [
            'token_hash' => hashToken($data['token']),
            'expires_at > ?' => now(true),
        ];

        $select = $this->sql
            ->select('password_reset_tokens')
            ->columns(['user_id'])
            ->where($where)
            ->limit(1);

        $resetToken = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        if ($resetToken === false) {
            throw new BadRequestException('The password reset token is invalid or expired');
        }

        $connection = $this->db->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            $consume = $this->sql
                ->delete('password_reset_tokens')
                ->where($where);

            $consumed = $this->sql
                ->prepareStatementForSqlObject($consume)
                ->execute();

            if ($consumed->getAffectedRows() !== 1) {
                $connection->rollback();
                return false;
            }

            if (!$this->updatePassword((int) $resetToken['user_id'], $data['new_password'])) {
                $connection->rollback();
                return false;
            }

            $connection->commit();

            return true;
        } catch (Throwable $e) {
            $connection->rollback();
            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public function change(int $userId, array $data): void
    {
        $user = $this->userRepository->findOne($userId);

        if ($user === null) {
            throw new NotFoundException();
        }

        $data = $this->validate($data, 'change');

        $password = $user['password'];
        $currentPassword = $data['current_password'];
        $newPassword = $data['new_password'];

        if (!checkPassword($currentPassword, $password)) {
            throw new BadRequestException('Current password is incorrect');
        }

        if (checkPassword($newPassword, $password)) {
            throw new BadRequestException('New password must be different from the current password');
        }

        $passwordChangedAt = $user['password_changed_at'];
        $cooldownCutoff = now()->sub(new DateInterval('PT24H'));
        $cooldownException = new BadRequestException('Password can only be changed once every 24 hours');

        if ($passwordChangedAt !== null && parseDateTime($passwordChangedAt) > $cooldownCutoff) {
            throw $cooldownException;
        }

        $connection = $this->db->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            if (!$this->updatePassword($userId, $newPassword, $cooldownCutoff)) {
                throw $cooldownException;
            }

            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollback();
            throw $e;
        }
    }

    protected function updatePassword(
        int $userId,
        string $password,
        ?DateTimeImmutable $changedBefore = null
    ): bool {
        $now = now(true);
        $where = ['id' => $userId];

        if ($changedBefore !== null) {
            $where['(password_changed_at IS NULL OR password_changed_at <= ?)'] = $changedBefore->format(DATE_FORMAT_DB);
        }

        $result = $this->userRepository
            ->update(
                [
                    'password' => hashPassword($password),
                    'password_changed_at' => $now,
                    'updated_at' => $now,
                ],
                $where
            );

        if ($result !== 1) {
            return false;
        }

        $revokeTokens = $this->sql
            ->delete('user_tokens')
            ->where(['user_id' => $userId]);

        $this->sql
            ->prepareStatementForSqlObject($revokeTokens)
            ->execute();

        return true;
    }
}
