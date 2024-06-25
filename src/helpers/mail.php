<?php

namespace hyper\helpers;

use Exception;

class mail
{
    private array $headers = [], $attachments = [];
    private string $to, $subject, $body;
    private bool $isHtml = true;

    public function from(string $email, string $name = ''): self
    {
        $from = empty($name) ? $email : "$name <$email>";
        $this->headers[] = "From: $from";
        return $this;
    }

    public function address(string $email, string $name = ''): self
    {
        $to = empty($name) ? $email : "$name <$email>";
        $this->to = $to;
        return $this;
    }

    public function replyTo(string $email, string $name = ''): self
    {
        $replyTo = empty($name) ? $email : "$name <$email>";
        $this->headers[] = "Reply-To: $replyTo";
        return $this;
    }

    public function cc(string $email, string $name = ''): self
    {
        $cc = empty($name) ? $email : "$name <$email>";
        $this->headers[] = "Cc: $cc";
        return $this;
    }

    public function bcc(string $email, string $name = ''): self
    {
        $bcc = empty($name) ? $email : "$name <$email>";
        $this->headers[] = "Bcc: $bcc";
        return $this;
    }

    public function attachment(string $filePath, string $name = ''): self
    {
        $this->attachments[] = ['path' => $filePath, 'name' => $name];
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function body(string $body, bool $isHtml = true): self
    {
        $this->isHtml = $isHtml;
        $this->body = $body;
        return $this;
    }

    public function send(): bool
    {
        return mail($this->to, $this->subject, $this->body, $this->buildHeaders());
    }

    private function buildHeaders(): string
    {
        if ($this->isHtml) {
            $this->headers[] = "MIME-Version: 1.0";
            $this->headers[] = "Content-type: text/html; charset=UTF-8";
        }

        if (!empty($this->attachments)) {
            $boundary = md5(time());
            $this->headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";

            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/" . ($this->isHtml ? "html" : "plain") . "; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($this->body)) . "\r\n";

            foreach ($this->attachments as $attachment) {
                $filePath = $attachment['path'];
                $fileName = empty($attachment['name']) ? basename($filePath) : $attachment['name'];
                $fileData = file_get_contents($filePath);

                if ($fileData === false) {
                    throw new Exception("Failed to read file: $filePath");
                }

                $body .= "--$boundary\r\n";
                $body .= "Content-Type: application/octet-stream; name=\"$fileName\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n\r\n";
                $body .= chunk_split(base64_encode($fileData)) . "\r\n";
            }

            $body .= "--$boundary--";
            $this->body = $body;
        }

        return implode("\r\n", $this->headers);
    }
}
