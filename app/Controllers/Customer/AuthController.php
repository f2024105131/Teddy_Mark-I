<?php

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

class AuthController extends Controller
{
    public function showLoginForm(): void
    {
        $this->view('auth/login', [], layout: 'guest');
    }

    public function login(): void
    {
        $this->requireCsrf();

        $email = $this->input('email', '');
        $password = $this->input('password', '');

        if (Auth::attemptCustomer($email, $password)) {
            $this->redirect('/customer/dashboard');
        }

        $this->flash('error', 'Invalid email or password.');
        $this->redirect('/login');
    }

    public function showRegisterForm(): void
    {
        $this->view('auth/register', [], layout: 'guest');
    }

    public function register(): void
    {
        $this->requireCsrf();

        $data = $this->all();

        $validator = Validator::make($data, [
            'full_name'      => 'required|max:255',
            'email'          => 'required|email|unique:customer,email',
            'phone'          => 'required|phone|unique:customer,phone',
            'password'       => 'required|min:8|confirmed',
            'address_house'  => 'required|max:50',
            'address_street' => 'required|max:100',
            'address_area'   => 'required|max:100',
            'city'           => 'required|max:50',
        ]);

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->errors();
            $_SESSION['old_input'] = $data;
            $this->back();
        }

        $token = bin2hex(random_bytes(32));

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO customer (
                full_name, email, phone, password_hash,
                address_house, address_street, address_area, city, landmark,
                account_status, email_verified, verification_token, token_expiry
            ) VALUES (
                :full_name, :email, :phone, :password_hash,
                :address_house, :address_street, :address_area, :city, :landmark,
                'pending', 0, :token, :token_expiry
            )
        ");

        $stmt->execute([
            'full_name'      => $data['full_name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'],
            'password_hash'  => Auth::hashPassword($data['password']),
            'address_house'  => $data['address_house'],
            'address_street' => $data['address_street'],
            'address_area'   => $data['address_area'],
            'city'           => $data['city'],
            'landmark'       => $data['landmark'] ?? null,
            'token'          => $token,
            'token_expiry'   => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);

        $this->sendVerificationEmail($data['email'], $token);

        $this->flash('success', 'Account created. Please check your email to verify your account.');
        $this->redirect('/login');
    }

    public function verifyEmail(string $token): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM customer WHERE verification_token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        $customer = $stmt->fetch();

        if (!$customer || strtotime($customer['token_expiry']) < time()) {
            $this->flash('error', 'This verification link is invalid or has expired.');
            $this->redirect('/login');
        }

        $db->prepare("
            UPDATE customer
            SET email_verified = 1, account_status = 'active', verification_token = NULL, token_expiry = NULL
            WHERE customer_id = :id
        ")->execute(['id' => $customer['customer_id']]);

        $this->flash('success', 'Your email has been verified. You can now log in.');
        $this->redirect('/login');
    }

    public function showForgotPasswordForm(): void
    {
        $this->view('auth/forgot_password', [], layout: 'guest');
    }

    public function forgotPassword(): void
    {
        $this->requireCsrf();

        $email = $this->input('email', '');

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM customer WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $customer = $stmt->fetch();

        if ($customer) {
            $token = bin2hex(random_bytes(32));

            $db->prepare("
                INSERT INTO password_reset (customer_id, token, token_expiry)
                VALUES (:customer_id, :token, :token_expiry)
            ")->execute([
                'customer_id'  => $customer['customer_id'],
                'token'        => $token,
                'token_expiry' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ]);

            $this->sendPasswordResetEmail($email, $token);
        }

        $this->flash('success', 'If that email exists in our system, a reset link has been sent.');
        $this->redirect('/login');
    }

    public function showResetPasswordForm(string $token): void
    {
        $this->view('auth/reset_password', ['token' => $token], layout: 'guest');
    }

    public function resetPassword(): void
    {
        $this->requireCsrf();

        $data = $this->all();

        $validator = Validator::make($data, [
            'token'    => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->errors();
            $this->back();
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM password_reset
            WHERE token = :token AND used = 0 AND customer_id IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute(['token' => $data['token']]);
        $reset = $stmt->fetch();

        if (!$reset || strtotime($reset['token_expiry']) < time()) {
            $this->flash('error', 'This reset link is invalid or has expired.');
            $this->redirect('/login');
        }

        $db->prepare("UPDATE customer SET password_hash = :password_hash WHERE customer_id = :id")
           ->execute([
               'password_hash' => Auth::hashPassword($data['password']),
               'id'            => $reset['customer_id'],
           ]);

        $db->prepare("UPDATE password_reset SET used = 1, used_at = NOW() WHERE reset_id = :id")
           ->execute(['id' => $reset['reset_id']]);

        $this->flash('success', 'Your password has been reset. You can now log in.');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }

    private function sendVerificationEmail(string $email, string $token): void
    {
        $link = ($_ENV['APP_URL'] ?? 'http://localhost') . '/verify-email/' . $token;
        $subject = 'Verify your Teddy_Mark-I account';
        $body = "Click the link below to verify your account:\n\n{$link}";
        @mail($email, $subject, $body);
    }

    private function sendPasswordResetEmail(string $email, string $token): void
    {
        $link = ($_ENV['APP_URL'] ?? 'http://localhost') . '/reset-password/' . $token;
        $subject = 'Reset your Teddy_Mark-I password';
        $body = "Click the link below to reset your password:\n\n{$link}";
        @mail($email, $subject, $body);
    }
}