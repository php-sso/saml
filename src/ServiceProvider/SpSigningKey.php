<?php

declare(strict_types=1);

namespace PhpSso\Saml\ServiceProvider;

use InvalidArgumentException;
use RuntimeException;

/**
 * The key a service provider signs its requests with, and the certificate a company's provider checks them against.
 * The certificate goes into our metadata. A `$nextCertificate` is published next to it before it replaces the first,
 * so companies can add it to their provider ahead of a change of key.
 */
final class SpSigningKey
{
    /**
     * @param string $privateKey PEM, the key requests are signed with
     * @param string $certificate PEM, the certificate for that key
     * @param string|null $nextCertificate PEM, the certificate of the key that replaces this one (published only)
     * @throws InvalidArgumentException when a part is not what it should be, or the certificate is not for the key
     */
    public function __construct(
        #[\SensitiveParameter] public readonly string $privateKey,
        public readonly string $certificate,
        public readonly ?string $nextCertificate = null,
    ) {
        $key = @openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new InvalidArgumentException('The signing key is not a readable private key.');
        }
        if (@openssl_x509_read($certificate) === false) {
            throw new InvalidArgumentException('The signing certificate is not a readable certificate.');
        }
        if (!openssl_x509_check_private_key($certificate, $key)) {
            throw new InvalidArgumentException('The signing certificate does not belong to the signing key.');
        }
        if ($nextCertificate !== null && @openssl_x509_read($nextCertificate) === false) {
            throw new InvalidArgumentException('The next signing certificate is not a readable certificate.');
        }
    }

    /**
     * A new RSA key and a self-signed certificate for it. Providers read the key from our metadata and do not need a
     * certificate authority; most only look at the validity dates, so it is long lived.
     *
     * @return array{privateKey: string, certificate: string}
     * @throws RuntimeException
     */
    public static function generate(string $commonName, int $days = 3650, int $bits = 3072): array
    {
        $key = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = $key === false ? false : openssl_csr_new(['commonName' => $commonName], $key, ['digest_alg' => 'sha256']);
        if (!$key instanceof \OpenSSLAsymmetricKey || !$csr instanceof \OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('Could not make a signing key.');
        }
        $certificate = openssl_csr_sign($csr, null, $key, $days, ['digest_alg' => 'sha256']);
        if (!$certificate instanceof \OpenSSLCertificate || !openssl_pkey_export($key, $privateKey) || !openssl_x509_export($certificate, $pem) || !is_string($privateKey) || !is_string($pem)) {
            throw new RuntimeException('Could not make a signing certificate.');
        }

        return ['privateKey' => $privateKey, 'certificate' => $pem];
    }

    /** The SHA-256 fingerprint of a certificate, as providers show it (upper case hex pairs separated by colons). */
    public static function fingerprint(string $certificate): string
    {
        $hash = openssl_x509_fingerprint($certificate, 'sha256');
        if ($hash === false) {
            throw new InvalidArgumentException('The certificate is not readable.');
        }

        return implode(':', str_split(strtoupper($hash), 2));
    }
}
