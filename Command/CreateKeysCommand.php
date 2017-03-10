<?php

namespace Lexik\Bundle\JWTAuthenticationBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Command to create the keys necessary for JWT token creation and validation.
 *
 * @author John Pancoast <johnpancoaster@gmail.com>
 */
class CreateKeysCommand extends ContainerAwareCommand
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('lexik:jwt:create-keys')
            ->setDescription('Create keys necessary for JWT token creation.')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force recreation of existing keys'
            )
        ;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $fs = new Filesystem();

        $prvKeyPath = $this->getContainer()->getParameter('jwt_private_key_path');
        $pubKeyPath = $this->getContainer()->getParameter('jwt_public_key_path');
        $pass = $this->getContainer()->getParameter('jwt_key_pass_phrase');

        if (($fs->exists($prvKeyPath) || $fs->exists($pubKeyPath)) && !$input->getOption('force')) {
            $io->caution('This will recreate keys used for JWT token creation which will invalidate all issued JWT tokens.');

            $response = strtolower($io->ask('Are you sure you want to do this?'));

            if ($response != 'y' && $response != 'yes') {
                $io->writeln('Exiting...');

                return;
            }
        }

        try {
            $sslCmd = trim((new Process('which openssl'))->mustRun()->getOutput());
        } catch (\Exception $e) {
            throw new \RuntimeException(
                sprintf(
                    'Command requires openssl',
                    $this->getName()
                )
            );
        }

        $io->writeln('<info>Creating keys</info>');

        // mkdir then write private key, pass the password to the command's stdin so the password isn't shown in ps.
        (new Process(
            sprintf(
                '/bin/mkdir -p %s && %s genrsa -passout stdin -out %s -aes256 4096',
                dirname($prvKeyPath),
                $sslCmd,
                $prvKeyPath
            )
        ))->setInput($pass)->mustRun();

        $io->writeln('');
        $io->writeln('<info>Created private key:</info>');
        $io->writeln($prvKeyPath);

        // mkdir then write public key, pass the password to the command's stdin so the password isn't shown in ps.
        (new Process(
            sprintf(
                '/bin/mkdir -p %s && %s rsa -passin stdin -pubout -in %s -out %s',
                dirname($pubKeyPath),
                $sslCmd,
                $prvKeyPath,
                $pubKeyPath
            )
        ))->setInput($pass)->mustRun();

        $io->writeln('');
        $io->writeln('<info>Created public key:</info>');
        $io->writeln($pubKeyPath);
    }
}
