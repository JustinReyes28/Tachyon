<?php
/**
 * EmailNotifier Class
 * 
 * Handles sending email notifications using PHPMailer.
 */

// Include PHPMailer classes
require_once dirname(__DIR__) . '/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/phpmailer/src/SMTP.php';
require_once dirname(__DIR__) . '/phpmailer/src/Exception.php';

// Include email configuration
require_once dirname(__DIR__) . '/config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailNotifier
{
    private $mailer;
    private $templateDir;

    /**
     * Constructor - initializes PHPMailer with SMTP settings
     */
    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->templateDir = dirname(__DIR__) . '/templates/email/';

        // SMTP Configuration
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = SMTP_USER;
        $this->mailer->Password = SMTP_PASS;
        $this->mailer->SMTPSecure = SMTP_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = SMTP_PORT;

        // Set timeouts to prevent hung processes
        $this->mailer->Timeout = 30;
        $smtp = $this->mailer->getSMTPInstance();
        if ($smtp) {
            $smtp->Timelimit = 10;
        }

        // Default sender
        $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    /**
     * Send a due date reminder email
     * 
     * @param string $userEmail Recipient email address
     * @param string $username Recipient's username
     * @param string $taskName Name of the task
     * @param string $dueDate Due date of the task (Y-m-d format)
     * @return bool True on success, false on failure
     */
    public function sendDueDateReminder($userEmail, $username, $taskName, $dueDate)
    {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userEmail, $username);

            // Set subject
            $this->mailer->Subject = "Reminder: Task \"$taskName\" is due soon!";

            // Load and process template
            $templateFile = $this->templateDir . 'due_date_reminder.html';

            if (!file_exists($templateFile)) {
                error_log("EmailNotifier: Template not found at $templateFile");
                return false;
            }

            $template = file_get_contents($templateFile);

            // Format the due date for display
            $formattedDate = date('F j, Y', strtotime($dueDate));

            // Replace placeholders - sanitize all user inputs
            $body = str_replace(
                ['{{username}}', '{{task_name}}', '{{due_date}}', '{{app_url}}'],
                [htmlspecialchars($username, ENT_NOQUOTES), htmlspecialchars($taskName, ENT_NOQUOTES), $formattedDate, htmlspecialchars(APP_URL, ENT_NOQUOTES)],
                $template
            );

            $this->mailer->Body = $body;

            // Plain text alternative - sanitize user inputs to prevent injection
            $this->mailer->AltBody = "Hi " . htmlspecialchars($username, ENT_NOQUOTES) . ",\n\n" .
                "This is a reminder that your task \"" . htmlspecialchars($taskName, ENT_NOQUOTES) . "\" is due on $formattedDate.\n\n" .
                "Visit " . htmlspecialchars(APP_URL, ENT_NOQUOTES) . " to manage your tasks.\n\n" .
                "- Tachyon Task Manager";

            // Send the email
            $this->mailer->send();

            return true;

        } catch (Exception $e) {
            error_log("EmailNotifier Error: Failed to send due date reminder to $userEmail. Error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }

    /**
     * Test the SMTP connection
     * 
     * @return bool True if connection successful
     */
    public function testConnection()
    {
        try {
            // Check if SMTP settings are configured
            if (empty(SMTP_HOST) || empty(SMTP_USER)) {
                error_log("EmailNotifier: SMTP not configured");
                return false;
            }

            $this->mailer->SMTPDebug = SMTP::DEBUG_CONNECTION;
            ob_start();
            $result = $this->mailer->smtpConnect();
            $debug = ob_get_clean();

            if ($result) {
                $this->mailer->smtpClose();
                return true;
            }

            error_log("EmailNotifier: SMTP connection test failed - $debug");
            return false;

        } catch (Exception $e) {
            error_log("EmailNotifier: SMTP connection test exception - " . $e->getMessage());
            return false;
        }
    }
    /**
     * Send a verification email
     *
     * @param string $userEmail Recipient email address
     * @param string $username Recipient's username
     * @param string $verificationLink Verification URL
     * @return bool True on success, false on failure
     */
    public function sendVerificationEmail($userEmail, $username, $verificationLink)
    {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userEmail, $username);

            // Set subject
            $this->mailer->Subject = "Verify your email address - Tachyon";

            // Load and process template
            $templateFile = $this->templateDir . 'verification_email.html';

            if (!file_exists($templateFile)) {
                error_log("EmailNotifier: Template not found at $templateFile");
                return false;
            }

            $template = file_get_contents($templateFile);

            // Replace placeholders
            // Note: complex links might need careful handling, but generally htmlspecialchars is safer for HTML output
            $body = str_replace(
                ['{{username}}', '{{verification_link}}'],
                [htmlspecialchars($username, ENT_NOQUOTES), htmlspecialchars($verificationLink, ENT_NOQUOTES)],
                $template
            );

            $this->mailer->Body = $body;

            // Plain text alternative
            $this->mailer->AltBody = "Hi " . htmlspecialchars($username, ENT_NOQUOTES) . ",\n\n" .
                "Welcome to Tachyon Task Manager!\n\n" .
                "Please verify your email address by clicking the link below:\n" .
                "$verificationLink\n\n" .
                "If you didn't create an account, you can ignore this email.\n\n" .
                "- Tachyon Task Manager";

            // Send the email
            $this->mailer->send();

            return true;

        } catch (Exception $e) {
            error_log("EmailNotifier Error: Failed to send verification email to $userEmail. Error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }

    /**
     * Send a verification code email
     *
     * @param string $userEmail Recipient email address
     * @param string $username Recipient's username
     * @param string $code 6-digit verification code
     * @return bool True on success, false on failure
     */
    public function sendVerificationCode($userEmail, $username, $code)
    {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userEmail, $username);

            // Set subject
            $this->mailer->Subject = "Your Verification Code - Tachyon";

            // Load and process template
            $templateFile = $this->templateDir . 'verification_code.html';

            if (!file_exists($templateFile)) {
                error_log("EmailNotifier: Template not found at $templateFile");
                return false;
            }

            $template = file_get_contents($templateFile);

            // Replace placeholders
            $body = str_replace(
                ['{{username}}', '{{verification_code}}'],
                [htmlspecialchars($username, ENT_NOQUOTES), htmlspecialchars($code, ENT_NOQUOTES)],
                $template
            );

            $this->mailer->Body = $body;

            // Plain text alternative
            $this->mailer->AltBody = "Hi " . htmlspecialchars($username, ENT_NOQUOTES) . ",\n\n" .
                "Welcome to Tachyon Task Manager!\n\n" .
                "Your verification code is: $code\n\n" .
                "This code expires in 10 minutes.\n\n" .
                "If you didn't create an account, you can ignore this email.\n\n" .
                "- Tachyon Task Manager";

            // Send the email
            $this->mailer->send();

            return true;

        } catch (Exception $e) {
            error_log("EmailNotifier Error: Failed to send verification code to $userEmail. Error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    /**
     * Send a change password verification code email
     *
     * @param string $userEmail Recipient email address
     * @param string $username Recipient's username
     * @param string $code 6-digit verification code
     * @return bool True on success, false on failure
     */
    public function sendChangePasswordCode($userEmail, $username, $code)
    {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userEmail, $username);

            // Set subject
            $this->mailer->Subject = "Password Change Verification - Tachyon";

            // Load and process template
            $templateFile = $this->templateDir . 'change_password_code.html';

            if (!file_exists($templateFile)) {
                error_log("EmailNotifier: Template not found at $templateFile");
                return false;
            }

            $template = file_get_contents($templateFile);

            // Replace placeholders
            $body = str_replace(
                ['{{username}}', '{{verification_code}}'],
                [htmlspecialchars($username, ENT_NOQUOTES), htmlspecialchars($code, ENT_NOQUOTES)],
                $template
            );

            $this->mailer->Body = $body;

            // Plain text alternative
            $this->mailer->AltBody = "Hi " . htmlspecialchars($username, ENT_NOQUOTES) . ",\n\n" .
                "You requested to change your password.\n\n" .
                "Your verification code is: $code\n\n" .
                "This code expires in 10 minutes.\n\n" .
                "If you didn't request this, please secure your account immediately.\n\n" .
                "- Tachyon Task Manager";

            // Send the email
            $this->mailer->send();

            return true;

        } catch (Exception $e) {
            error_log("EmailNotifier Error: Failed to send change password code to $userEmail. Error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }

    /**
     * Send a password reset email
     *
     * @param string $userEmail Recipient email address
     * @param string $resetLink Password reset URL
     * @return bool True on success, false on failure
     */
    public function sendPasswordResetEmail($userEmail, $resetLink)
    {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userEmail);

            // Set subject
            $this->mailer->Subject = "Reset Your Password - Tachyon";

            // Load and process template
            $templateFile = $this->templateDir . 'password_reset.html';

            if (!file_exists($templateFile)) {
                error_log("EmailNotifier: Template not found at $templateFile");
                return false;
            }

            $template = file_get_contents($templateFile);

            // Replace placeholders
            $body = str_replace(
                ['{{reset_link}}'],
                [htmlspecialchars($resetLink, ENT_NOQUOTES)],
                $template
            );

            $this->mailer->Body = $body;

            // Plain text alternative
            $this->mailer->AltBody = "Hi,\n\n" .
                "We received a request to reset your password.\n\n" .
                "Use the following link to reset it:\n" .
                "$resetLink\n\n" .
                "This link expires in 1 hour.\n\n" .
                "- Tachyon Task Manager";

            // Send the email
            $this->mailer->send();

            return true;

        } catch (Exception $e) {
            error_log("EmailNotifier Error: Failed to send password reset email to $userEmail. Error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
}