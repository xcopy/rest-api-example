<?php

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Validators\AuthValidator;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
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

        if ($this->isRateLimited($email, $ip)) {
            return JsonResponse::write($response, ['message' => 'Too many attempts. Try again later.'], 429)
                ->withHeader('Retry-After', (string) self::WINDOW_SECONDS);
        }

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

    private const WINDOW_SECONDS = 900;
    private const LIMITS = [
        'ip_failed' => 20,
        'ip_total' => 100,
        'email_failed' => 5,
        'email_success' => 5,
    ];

    private function isRateLimited(string $email, string $ip): bool
    {
        $select = $this->sql
            ->select('login_attempts')
            ->columns([
                'ip_failed' => new Expression('COALESCE(SUM(ip = ? AND successful = 0), 0)', $ip),
                'ip_total' => new Expression('COALESCE(SUM(ip = ?), 0)', $ip),
                'email_failed' => new Expression('COALESCE(SUM(email = ? AND successful = 0), 0)', $email),
                'email_success' => new Expression('COALESCE(SUM(email = ? AND successful = 1), 0)', $email),
            ])
            ->where(function (Where $where) use ($email, $ip) {
                $since = (new \DateTimeImmutable('-' . self::WINDOW_SECONDS . ' seconds', new \DateTimeZone('UTC')))
                    ->format('Y-m-d H:i:s');

                $where->expression('attempted_at > ?', $since);
                $where->expression('(email = ? OR ip = ?)', [$email, $ip]);
            });

        $row = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        foreach (self::LIMITS as $key => $max) {
            if ((int) $row[$key] >= $max) {
                return true;
            }
        }

        return false;
    }

    private function recordAttempt(string $email, string $ip, bool $successful): void
    {
        $insert = $this->sql
            ->insert('login_attempts')
            ->values([
                'email' => $email,
                'ip' => $ip,
                'successful' => $successful ? 1 : 0,
                'attempted_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                    ->format('Y-m-d H:i:s'),
            ]);

        $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();
    }
}
