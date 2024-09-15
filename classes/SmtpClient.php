<?php
class SmtpClient
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $socket;
    private $debug = false;
    private $secure = false;

    public function __construct($host, $port, $username, $password)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->secure = ($port == 465);
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    private function log($message)
    {
        if ($this->debug) {
            error_log($message);
        }
    }

    private function send($command)
    {
        $this->log("SEND: $command");
        if (fwrite($this->socket, $command . "\r\n") === false) {
            throw new Exception("Failed to send command: $command");
        }
    }

    private function receive()
    {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            $this->log("RECV: $line");
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    }

    private function expect($code)
    {
        $response = $this->receive();
        $responseCode = substr($response, 0, 3);
        if ($responseCode != $code) {
            throw new Exception("Expected $code, but got: $response");
        }
        return $response;
    }

    public function connect()
    {
        if ($this->secure) {
            $this->socket = fsockopen("ssl://" . $this->host, $this->port, $errno, $errstr, 30);
        } else {
            $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 30);
        }

        if (!$this->socket) {
            throw new Exception("Could not connect to $this->host:$this->port - $errstr ($errno)");
        }
        stream_set_timeout($this->socket, 30);
        $this->expect(220);

        $this->send("EHLO " . gethostname());
        $this->expect(250);

        if (!$this->secure) {
            $this->send("STARTTLS");
            $response = $this->receive();
            $responseCode = substr($response, 0, 3);
            if ($responseCode == "220") {
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception("Failed to enable TLS encryption");
                }
                $this->send("EHLO " . gethostname());
                $this->expect(250);
            } elseif ($responseCode != "250") {
                throw new Exception("STARTTLS failed: $response");
            }
        }

        $this->send("AUTH LOGIN");
        $this->expect(334);

        $this->send(base64_encode($this->username));
        $this->expect(334);

        $this->send(base64_encode($this->password));
        $this->expect(235);
    }

    public function sendMail($from, $to, $subject, $body)
    {
        $this->send("MAIL FROM:<$from>");
        $this->expect(250);

        $this->send("RCPT TO:<$to>");
        $this->expect(250);

        $this->send("DATA");
        $this->expect(354);

        $message = "From: $from\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: $subject\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "\r\n";
        $message .= $body;
        $message .= "\r\n.";

        $this->send($message);
        $this->expect(250);
    }

    public function quit()
    {
        $this->send("QUIT");
        $this->expect(221);
        fclose($this->socket);
    }
}
