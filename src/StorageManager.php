<?php

declare(strict_types=1);

namespace Grandpa;

use Aws\S3\S3Client;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use Masbug\Flysystem\GoogleDriveAdapter;
use RoyVoetman\FlysystemGitlab\Client as GitlabClient;
use RoyVoetman\FlysystemGitlab\GitlabAdapter;

class StorageManager
{
    private Storage|null $ftp = null;
    private Storage|null $sftp = null;
    private Storage|null $s3 = null;
    private Storage|null $gitlab = null;
    private Storage|null $googleDrive = null;

    public function ftp(): Storage
    {
        if (!extension_loaded('ftp')) {
            throw new \RuntimeException(
                'The PHP "ftp" extension is required for FTP deployment but is not enabled. '
                . 'Enable it in your php.ini (extension=ftp) and restart, then try again.'
            );
        }

        return $this->ftp ??= new Storage(new FtpAdapter(
            FtpConnectionOptions::fromArray([
                'host' => (string) env('GRANDPA_FTP_HOST', ''),
                'username' => (string) env('GRANDPA_FTP_USERNAME', ''),
                'password' => (string) env('GRANDPA_FTP_PASSWORD', ''),
                'port' => (int) env('GRANDPA_FTP_PORT', 21),
                'root' => (string) env('GRANDPA_FTP_PATH', ''),
                'passive' => filter_var(env('GRANDPA_FTP_PASSIVE', true), FILTER_VALIDATE_BOOLEAN),
                'ssl' => false,
                'timeout' => 30,
            ]),
        ));
    }

    public function sftp(): Storage
    {
        return $this->sftp ??= new Storage(new SftpAdapter(
            new SftpConnectionProvider(
                host: (string) env('GRANDPA_SFTP_HOST', ''),
                username: (string) env('GRANDPA_SFTP_USERNAME', ''),
                password: env('GRANDPA_SFTP_PASSWORD') !== null ? (string) env('GRANDPA_SFTP_PASSWORD') : null,
                privateKey: env('GRANDPA_SFTP_PRIVATE_KEY') !== null ? (string) env('GRANDPA_SFTP_PRIVATE_KEY') : null,
                passphrase: env('GRANDPA_SFTP_PASSPHRASE') !== null ? (string) env('GRANDPA_SFTP_PASSPHRASE') : null,
                port: (int) env('GRANDPA_SFTP_PORT', 22),
                timeout: 30,
            ),
            (string) env('GRANDPA_SFTP_PATH', ''),
        ));
    }

    public function s3(): Storage
    {
        $config = [
            'credentials' => [
                'key' => (string) env('GRANDPA_S3_KEY', ''),
                'secret' => (string) env('GRANDPA_S3_SECRET', ''),
            ],
            'region' => (string) env('GRANDPA_S3_REGION', 'us-east-1'),
            'version' => 'latest',
            'use_path_style_endpoint' => filter_var(env('GRANDPA_S3_USE_PATH_STYLE', false), FILTER_VALIDATE_BOOLEAN),
        ];

        $endpoint = env('GRANDPA_S3_ENDPOINT');

        if ($endpoint !== null && $endpoint !== '') {
            $config['endpoint'] = (string) $endpoint;
        }

        $client = new S3Client($config);

        return $this->s3 ??= new Storage(new AwsS3V3Adapter(
            $client,
            (string) env('GRANDPA_S3_BUCKET', ''),
            (string) env('GRANDPA_S3_PATH', ''),
        ));
    }

    public function gitlab(): Storage
    {
        $client = new GitlabClient(
            (string) env('GRANDPA_GITLAB_PROJECT_ID', ''),
            (string) env('GRANDPA_GITLAB_BRANCH', 'main'),
            (string) env('GRANDPA_GITLAB_BASE_URL', 'https://gitlab.com'),
            env('GRANDPA_GITLAB_TOKEN') !== null ? (string) env('GRANDPA_GITLAB_TOKEN') : null,
        );

        return $this->gitlab ??= new Storage(new GitlabAdapter(
            $client,
            (string) env('GRANDPA_GITLAB_PATH', ''),
        ));
    }

    public function googleDrive(): Storage
    {
        $client = new GoogleClient();
        $client->setClientId((string) env('GRANDPA_GOOGLE_DRIVE_CLIENT_ID', ''));
        $client->setClientSecret((string) env('GRANDPA_GOOGLE_DRIVE_CLIENT_SECRET', ''));
        $client->refreshToken((string) env('GRANDPA_GOOGLE_DRIVE_REFRESH_TOKEN', ''));
        $client->setApplicationName('Grandpa');

        $service = new GoogleDriveService($client);

        return $this->googleDrive ??= new Storage(new GoogleDriveAdapter(
            $service,
            (string) env('GRANDPA_GOOGLE_DRIVE_PATH', ''),
        ));
    }
}
