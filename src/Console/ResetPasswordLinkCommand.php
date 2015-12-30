<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace App\Auth\Console;

use App\Auth\Libs\Auth;
use App\Auth\Models\UserLink;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetPasswordLinkCommand extends Command
{
    use \InjectApp;

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

        $userModel = Auth::USER_MODEL;
        $user = $userModel::where('user_email', $email)->first();
        if (!$user) {
            $output->writeln("User not found for $email");

            return 1;
        }

        $link = new UserLink();
        $link->uid = $user->id();
        $link->link_type = UserLink::FORGOT_PASSWORD;
        if (!$link->grantAllPermissions()->save()) {
            $output->writeln("Could not create reset password link for $email");

            return 1;
        }

        $output->writeln("Reset password link for $email: {$link->url()}");

        return 0;
    }
}
