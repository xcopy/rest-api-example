<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\HasFactory;
use App\Db\Adapter;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Repositories\EmailVerificationTokenRepository;
use App\Repositories\UserRepository;
use App\Services\Concerns\ValidatesData;
use App\Validators\EmailVerificationValidator;
use DI\Attribute\Inject;
use Slim\Interfaces\RouteParserInterface;

final class EmailVerificationService
{
    use HasFactory;
    use ValidatesData;

    #[Inject]
    private Adapter $db;

    #[Inject]
    private EmailService $email;

    #[Inject]
    private EmailVerificationTokenRepository $repository;

    #[Inject]
    private UserRepository $userRepository;

    #[Inject]
    private RouteParserInterface $routeParser;

    protected function validatorClass(): string
    {
        return EmailVerificationValidator::class;
    }

    /** @param-out string $token */
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

        $this->db->transaction(function () use ($token, $userId, $expiresAt) {
            $this->repository->delete(['user_id' => $userId]);
            $this->repository->insert([
                'user_id' => $userId,
                'token_hash' => hashToken($token),
                'expires_at' => $expiresAt->format(DATE_FORMAT_DB),
            ]);
        });

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

        return $this->db->transaction(function () use ($now, $where) {
            $verificationToken = $this->repository->findOne($where);
            $invalidException = new BadRequestException('The verification token is invalid or expired');

            if ($verificationToken === null) {
                throw $invalidException;
            }

            $consumed = $this->repository->delete($where);

            if ($consumed !== 1) {
                throw $invalidException;
            }

            $result = $this->userRepository->update(
                [
                    'email_verified_at' => $now,
                    'updated_at' => $now,
                ],
                ['id' => (int) $verificationToken['user_id']]
            );

            if ($result !== 1) {
                throw new NotFoundException('User with this verification token not found');
            }

            return true;
        });
    }
}
