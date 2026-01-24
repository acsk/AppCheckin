<?php
require 'vendor/autoload.php';

$resend = Resend::client('re_GnoVQKb2_39XJns5yozy9PHuU81fU7KJf');

$result = $resend->emails->send([
    'from' => 'App Check-in <mail@appcheckin.com.br>',
    'to' => ['andre@codiub.com.br'],
    'subject' => 'Teste Resend - Dominio Verificado',
    'html' => '<h1>Sucesso!</h1><p>Email enviado do dominio appcheckin.com.br via Resend.</p>'
]);

if ($result && $result->id) {
    echo "✅ Sucesso! ID: " . $result->id . "\n";
} else {
    echo "Erro\n";
    print_r($result);
}
