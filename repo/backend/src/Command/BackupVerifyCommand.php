<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:backup:verify',
    description: 'Run mysqldump checksum verification for backup integrity',
)]
final class BackupVerifyCommand extends Command
{
    public function __construct(
        private readonly string $databaseUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('backup-dir', 'd', InputOption::VALUE_OPTIONAL, 'Directory containing backup files', '/var/backups/meridian');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $backupDir = (string) $input->getOption('backup-dir');

        $io->info(sprintf('Verifying backups in %s...', $backupDir));

        if (!is_dir($backupDir)) {
            $io->warning(sprintf('Backup directory %s does not exist. Creating a fresh dump for verification...', $backupDir));

            // Parse database URL for mysqldump
            $parsed = parse_url($this->databaseUrl);
            if ($parsed === false || !isset($parsed['host'], $parsed['path'])) {
                $io->error('Cannot parse DATABASE_URL. Ensure it is a valid MySQL DSN.');

                return Command::FAILURE;
            }

            $host = $parsed['host'];
            $port = $parsed['port'] ?? 3306;
            $user = $parsed['user'] ?? 'root';
            $pass = $parsed['pass'] ?? '';
            $dbName = ltrim($parsed['path'], '/');

            // Run mysqldump and compute checksum
            $dumpCommand = sprintf(
                'mysqldump -h %s -P %d -u %s %s %s 2>/dev/null | md5sum',
                escapeshellarg($host),
                $port,
                escapeshellarg($user),
                $pass !== '' ? '-p' . escapeshellarg($pass) : '',
                escapeshellarg($dbName),
            );

            $process = Process::fromShellCommandline($dumpCommand);
            $process->setTimeout(600);
            $process->run();

            if (!$process->isSuccessful()) {
                $io->error(sprintf('mysqldump failed: %s', $process->getErrorOutput()));

                return Command::FAILURE;
            }

            $checksum = trim(explode(' ', $process->getOutput())[0]);
            $io->success(sprintf('Dump checksum (MD5): %s', $checksum));

            return Command::SUCCESS;
        }

        // Verify existing backup files
        $files = glob($backupDir . '/*.sql.gz') ?: [];

        if (count($files) === 0) {
            $io->warning('No .sql.gz backup files found.');

            return Command::SUCCESS;
        }

        $verified = 0;
        $failed = 0;

        foreach ($files as $file) {
            $checksumFile = $file . '.md5';

            if (!file_exists($checksumFile)) {
                $io->warning(sprintf('No checksum file for %s — skipping.', basename($file)));
                continue;
            }

            $expectedChecksum = trim((string) file_get_contents($checksumFile));

            $process = Process::fromShellCommandline(sprintf('md5sum %s', escapeshellarg($file)));
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                $io->error(sprintf('Cannot compute checksum for %s.', basename($file)));
                $failed++;
                continue;
            }

            $actualChecksum = trim(explode(' ', $process->getOutput())[0]);

            if ($actualChecksum === $expectedChecksum) {
                $io->writeln(sprintf('  [OK] %s', basename($file)));
                $verified++;
            } else {
                $io->error(sprintf('  [FAIL] %s — expected %s, got %s', basename($file), $expectedChecksum, $actualChecksum));
                $failed++;
            }
        }

        if ($failed > 0) {
            $io->error(sprintf('Backup verification completed with %d failure(s).', $failed));

            return Command::FAILURE;
        }

        $io->success(sprintf('All %d backup file(s) verified successfully.', $verified));

        return Command::SUCCESS;
    }
}
