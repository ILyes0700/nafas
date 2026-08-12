<?php
header('Content-Type: text/plain; charset=utf-8');
$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'llama-3.3-70b-versatile',
        'messages' => [['role' => 'user', 'content' => 'dis bonjour']],
        'max_tokens' => 60,
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer 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',
    ],
    CURLOPT_TIMEOUT => 15,
]);
$raw = curl_exec($ch);
echo "HTTP: "  . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
echo "ERRNO: " . curl_errno($ch) . "\n";
echo "ERROR: " . curl_error($ch) . "\n";
echo "BODY:\n" . $raw . "\n";
curl_close($ch);