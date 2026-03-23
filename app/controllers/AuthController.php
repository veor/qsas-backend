<?php
declare(strict_types=1);
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends \Phalcon\Mvc\Controller
{

    public function initialize()
    {
        // Allow CORS for Angular
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Range, Content-Disposition, Content-Type, Authorization');
        $this->response->setContentType('application/json', 'UTF-8');
    }

    public function loginAction()
    {
        try {
            $data = $this->request->getJsonRawBody();
            
            if (!$data || !isset($data->idNo) || !isset($data->password)) {
                return $this->response->setJsonContent([
                    'success' => false,
                    'message' => 'ID No and password are required'
                ]);
            }

            $idNo = $data->idNo;
            $password = $data->password;

            $user = Users::findFirst([
                'conditions' => 'idNo = ?1',
                'bind' => [1 => $idNo]
            ]);

            if (!$user) {
                return $this->response
                    ->setStatusCode(401, 'Unauthorized')
                    ->setJsonContent(['success' => false, 'message' => 'Invalid credentials']);
            }

            if ($user->is_locked) {
                return $this->response
                    ->setStatusCode(403, 'Forbidden')
                    ->setJsonContent(['success' => false, 'message' => 'Your account is locked.']);
            }

            if (!password_verify($password, $user->password)) {
                return $this->response
                    ->setStatusCode(401, 'Unauthorized')
                    ->setJsonContent(['success' => false, 'message' => 'Invalid credentials']);
            }

            // Decode permissions safely
            $permissions = $user->permissions;

            if (is_string($permissions)) {
                $permissions = json_decode($permissions, true);
            }

            if (!is_array($permissions)) {
                $permissions = [];
            }

            // Generate JWT token
            $config = $this->config;
            $payload = [
                'user_id' => $user->id,
                'idNo' => $user->idNo,
                'permissions' => $permissions,
                'iat' => time(),
                'exp' => time() + $config->jwt->expiration
            ];

            $token = JWT::encode($payload, $config->jwt->secret, 'HS256');

            return $this->response->setJsonContent([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'idNo' => $user->idNo,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'permissions' => $permissions,
                    'avatar'      => $user->avatar ? $user->avatar : null,
                ],
                'message' => 'Login successful'
            ]);

        } catch (Exception $e) {
            return $this->response->setJsonContent([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }

     public function verifyTokenAction()
    {
        try {
            $token = $this->getAuthToken();
            
            if (!$token) {
                return $this->response->setJsonContent([
                    'success' => false,
                    'message' => 'No token provided'
                ]);
            }

            $config = $this->config;
            $decoded = JWT::decode($token, new Key($config->jwt->secret, 'HS256'));

            return $this->response->setJsonContent([
                'success' => true,
                'user' => [
                    'id' => $decoded->user_id,
                    'idNo' => $decoded->idNo,
                    'permissions' => $decoded->permissions
                ]
            ]);

        } catch (Exception $e) {
            return $this->response->setJsonContent([
                'success' => false,
                'message' => 'Invalid token'
            ]);
        }
    }

    private function getAuthToken()
    {
        $headers = $this->request->getHeaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (strpos($authHeader, 'Bearer ') === 0) {
                return substr($authHeader, 7);
            }
        }
        
        return null;
    }

    private function checkPermission($requiredPermission)
    {
        $token = $this->getAuthToken();
        if (!$token) return false;

        $config = $this->config;
        try {
            $decoded = JWT::decode($token, new Key($config->jwt->secret, 'HS256'));
            $permissions = $decoded->permissions ?? [];

            return in_array($requiredPermission, $permissions);
        } catch (Exception $e) {
            return false;
        }
    }

}

