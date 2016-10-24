<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Console;

use Infuse\HasApp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetPasswordLinkCommand extends Command
{
    use HasApp;

    protected function configure()
    {
        $this
            ->setName('reset-password-link')
            ->setDescription('Generates a forgot password link for a user (does not send it)')
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'User\'s email address'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');

        $userClass = $this->app['auth']->getUserClass();
        $user = $userClass::where('email', $email)->first();
        if (!$user) {
            $output->writeln("User not found for $email");

            return 1;
        }

        $link = $this->app['auth']->getPasswordReset()
                                  ->buildLink($user->id(), 'N/A', 'Infuse/Console');

        $output->writeln("Reset password link for $email: {$link->url()}");

        return 0;
    }
}
