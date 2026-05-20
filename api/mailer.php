<?php
require_once __DIR__ . '/config.php';

/**
 * SSL SMTP ile mail gönderir (PHPMailer gerektirmez).
 * Döner: true | string (hata mesajı)
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool|string {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    $from = SMTP_FROM;
    $name = SMTP_NAME;

    $boundary = '----=_Part_' . md5(uniqid());
    $plainBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));

    $headers  = "From: =?UTF-8?B?" . base64_encode($name) . "?= <{$from}>\r\n";
    $headers .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: AkgulMailer/1.0\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($plainBody)) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ]]);

    $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) return "Bağlantı hatası: {$errstr} ({$errno})";

    $read = fn() => fgets($sock, 512);
    $send = fn($cmd) => fputs($sock, $cmd . "\r\n");

    $read(); // 220 greeting
    $send("EHLO " . gethostname());
    while ($line = $read()) { if (substr($line, 3, 1) === ' ') break; }

    $send("AUTH LOGIN");
    $read();
    $send(base64_encode($user));
    $read();
    $send(base64_encode($pass));
    $authResp = $read();
    if (substr($authResp, 0, 3) !== '235') {
        fclose($sock); return "Auth hatası: {$authResp}";
    }

    $send("MAIL FROM:<{$from}>");
    $read();
    $send("RCPT TO:<{$toEmail}>");
    $read();
    $send("DATA");
    $read();
    $send($headers . "\r\n" . $body . "\r\n.");
    $dataResp = $read();
    $send("QUIT");
    fclose($sock);

    return substr($dataResp, 0, 3) === '250' ? true : "Gönderim hatası: {$dataResp}";
}

/**
 * E-posta doğrulama maili gönderir.
 */
function sendVerificationMail(string $toEmail, string $toName, string $token): bool|string {
    $link = "https://akgulyayinevi.com/api/verify.php?token=" . urlencode($token);
    $html = "
    <div style='font-family:Georgia,serif;max-width:520px;margin:0 auto;background:#0F0E0B;color:#E8DCC8;border-radius:10px;overflow:hidden'>
      <div style='background:#1a1a16;padding:28px 32px;border-bottom:1px solid rgba(200,169,110,.2)'>
        <div style='font-size:1.2rem;font-weight:700;color:#C8A96E;letter-spacing:.04em'>Akgül Yayınevi</div>
        <div style='font-size:.75rem;color:rgba(200,169,110,.5);margin-top:2px'>Adana'nın Edebiyat Evi</div>
      </div>
      <div style='padding:32px'>
        <p style='margin:0 0 12px;font-size:.95rem'>Merhaba <strong>" . htmlspecialchars($toName) . "</strong>,</p>
        <p style='margin:0 0 24px;font-size:.88rem;color:rgba(232,220,200,.75);line-height:1.6'>
          Akgül Yayınevi'ne hoş geldiniz! Hesabınızı etkinleştirmek için aşağıdaki butona tıklayın.
        </p>
        <div style='text-align:center;margin:28px 0'>
          <a href='{$link}' style='display:inline-block;background:#C8A96E;color:#0F0E0B;text-decoration:none;padding:13px 32px;border-radius:6px;font-weight:700;font-size:.9rem;letter-spacing:.04em'>
            E-Postamı Doğrula
          </a>
        </div>
        <p style='font-size:.75rem;color:rgba(200,169,110,.45);margin:20px 0 0;line-height:1.6'>
          Bu link 24 saat geçerlidir. Eğer bu hesabı siz oluşturmadıysanız bu maili dikkate almayın.<br><br>
          <a href='{$link}' style='color:rgba(200,169,110,.5);word-break:break-all'>{$link}</a>
        </p>
      </div>
      <div style='background:#1a1a16;padding:16px 32px;border-top:1px solid rgba(200,169,110,.1);font-size:.7rem;color:rgba(200,169,110,.35);text-align:center'>
        Akgül Yayınevi · akgulyayinevi.com · bilgi@akgulyayinevi.com
      </div>
    </div>";

    return sendMail($toEmail, $toName, 'E-posta Adresinizi Doğrulayın — Akgül Yayınevi', $html);
}
