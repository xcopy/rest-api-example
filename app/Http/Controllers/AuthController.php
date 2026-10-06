<?php

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Validators\AuthValidator;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Leaf\Form;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    private Sql $sql;

    private AuthValidator $validator;

    public function __construct(Adapter $db, Form $form)
    {
        $this->sql = new Sql($db);
        $this->validator = new AuthValidator($form);
    }

    public function login(Request $request, Response $response): Response
    {
        $credentials = $this->validator->validate($request->getParsedBody() ?? [], 'login');

        if ($credentials === false) {
            return JsonResponse::write($response, $this->validator->getErrors(), 422);
        }

        $email = strtolower(trim($credentials['email']));
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';

        $select = $this->sql
            ->select('users')
            ->where(compact('email'))
            ->limit(1);

        $user = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        $successfull = $user && password_verify($credentials['password'], $user['password']);

        $this->recordAttempt($email, $ip, $successfull);

        if (!$successfull) {
            return JsonResponse::write($response, ['message' => 'Invalid email or password.'], 401);
        }

        $token = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $now->modify('+30 minutes');

        $insert = $this->sql
            ->insert('user_tokens')
            ->values([
                'user_id' => $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

        $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();

        return JsonResponse::write($response, [
            'access_token' => $token,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ]);
    }

    private function recordAttempt(string $email, string $ip, bool $successful): void
    {
        $insert = $this->sql
            ->insert('login_attempts')
            ->values([
                'email' => $email,
                'ip_address' => $ip,
                'successful' => $successful ? 1 : 0,
                'attempted_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ]);

        $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();
    }
}
