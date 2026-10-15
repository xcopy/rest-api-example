<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\HasFactory;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Repositories\UserRepository;
use App\Services\Concerns\ValidatesData;
use App\Validators\EmailVerificationValidator;
use DI\Attribute\Inject;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Slim\Interfaces\RouteParserInterface;
use Throwable;

class EmailVerificationService
{
    use HasFactory;
    use ValidatesData;

    #[Inject]
    private Adapter $db;

    #[Inject]
    private Sql $sql;

    #[Inject]
    private EmailService $email;

    #[Inject]
    private UserRepository $userRepository;

    #[Inject]
    private RouteParserInterface $routeParser;

    protected function validatorClass(): string
    {
        return EmailVerificationValidator::class;
    }

    public function send(string $email, ?string &$token = null): void
    {
        $data = $this->validate(compact('email'), 'send');

        $user = $this->userRepository->findOne(['email' => $data['email']]);

        if ($user === null) {
            throw new NotFoundException('User with this email not found');
        }

        if ($user['email_verified_at'] !== null) {
            throw new ConflictException('Email address is already verified');
        }

        $userId = $user['id'];
        $email = $user['email'];
        $token = generateToken();
        $expiresAt = now()->modify(config('email-verification.ttl'));

        $connection = $this->db->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            $revokeEmailVerificationTokens = $this->sql
                ->delete('email_verification_tokens')
                ->where(['user_id' => $userId]);

            $this->sql
                ->prepareStatementForSqlObject($revokeEmailVerificationTokens)
                ->execute();

            $insert = $this->sql
                ->insert('email_verification_tokens')
                ->values([
                    'user_id' => $userId,
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

        $url = rtrim(config('app.base_url'), '/') . $this->routeParser->urlFor('email-verification.verify');

        $message = $this->email
            ->compose()
            ->to($email)
            ->subject('Verify your email address')
            ->text(sprintf(
                "Hello,\n\nTo verify your email address, send a POST request to:\n%s\n\n"
                    . "with this JSON body:\n{\"token\":\"%s\"}\n\n"
                    . "This token expires at %s.\n",
                $url,
                $token,
                $expiresAt->format(DATE_ATOM)
            ));

        $this->email->send($message);
    }

    public function verify(string $token): bool
    {
        $data = $this->validate(compact('token'), 'verify');

        $now = now(true);
        $where = [
            'token_hash' => hashToken($data['token']),
            'expires_at > ?' => $now,
        ];

        $connection = $this->db->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            $select = $this->sql
                ->select('email_verification_tokens')
                ->columns(['user_id'])
                ->where($where)
                ->limit(1);

            $verificationToken = $this->sql
                ->prepareStatementForSqlObject($select)
                ->execute()
                ->current();

            if ($verificationToken === false) {
                throw new BadRequestException('The verification token is invalid or expired');
            }

            $consume = $this->sql
                ->delete('email_verification_tokens')
                ->where($where);

            $consumed = $this->sql
                ->prepareStatementForSqlObject($consume)
                ->execute();

            if ($consumed->getAffectedRows() !== 1) {
                $connection->rollback();
                return false;
            }

            $result = $this->userRepository
                ->update(
                    [
                        'email_verified_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['id' => (int) $verificationToken['user_id']]
                );

            if ($result !== 1) {
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
}
