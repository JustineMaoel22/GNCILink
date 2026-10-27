<?php
// Exercise the configuration loaders without loading real credentials or connecting to MySQL.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = file_get_contents(__DIR__ . '/../config/config.php');
$end = strpos($source, "\nloadEnv(__DIR__");
if ($end === false) throw new RuntimeException('Loader boundary not found.');
eval(substr($source, 5, $end - 5));
function secretsCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$fixture = tempnam(sys_get_temp_dir(), 'gnci-secrets-');
$key = 'GNCI_SECRETS_LOADER_TEST';
$previous = getenv($key);
$previousEnv = $_ENV[$key] ?? null;
try {
    loadSecretsArray($fixture . '.missing');
    file_put_contents($fixture, "$key=fallback\n");
    loadEnv($fixture);
    secretsCheck($_ENV[$key] === 'fallback', 'Missing secrets preserve .env fallback.');
    file_put_contents($fixture, '<?php return ["' . $key . '" => "hosted=value"];');
    loadSecretsArray($fixture);
    secretsCheck($_ENV[$key] === 'hosted=value' && getenv($key) === 'hosted=value', 'Hosted secrets override both configuration sources.');
    foreach (['<?php return false;', '<?php return ["' . $key . '" => "partial", "INVALID-KEY" => "hidden"];', '<?php return ["' . $key . '" => []];'] as $invalid) {
        file_put_contents($fixture, $invalid);
        $rejected = false;
        try { loadSecretsArray($fixture); } catch (RuntimeException $e) {
            $rejected = true;
            secretsCheck(!str_contains($e->getMessage(), 'hidden'), 'Errors omit credential values.');
        }
        secretsCheck($rejected && $_ENV[$key] === 'hosted=value', 'Invalid configuration rejected without partial changes.');
    }
    echo "PASS: optional secrets, .env fallback, hosted precedence and invalid entries.\n";
} finally {
    unlink($fixture);
    $previous === false ? putenv($key) : putenv($key . '=' . $previous);
    if ($previousEnv === null) unset($_ENV[$key]); else $_ENV[$key] = $previousEnv;
}
