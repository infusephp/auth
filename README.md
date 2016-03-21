auth
=============

[![Build Status](https://travis-ci.org/infusephp/auth.svg?branch=master&style=flat)](https://travis-ci.org/infusephp/auth)
[![Coverage Status](https://coveralls.io/repos/infusephp/auth/badge.svg?style=flat)](https://coveralls.io/r/infusephp/auth)
[![Latest Stable Version](https://poser.pugx.org/infuse/auth/v/stable.svg?style=flat)](https://packagist.org/packages/infuse/auth)
[![Total Downloads](https://poser.pugx.org/infuse/auth/downloads.svg?style=flat)](https://packagist.org/packages/infuse/auth)
[![HHVM Status](http://hhvm.h4cc.de/badge/infuse/auth.svg?style=flat)](http://hhvm.h4cc.de/package/infuse/auth)

Auth module for Infuse Framework

## Installation

1. Install the package with [composer](http://getcomposer.org):

   ```
   composer require infuse/auth
   ```

2. Add the service to `services` in your app's configuration:

   ```php
   'services' => [
	   // ...
	   'auth' => 'App\Auth\Services\Auth'
	   // ...
   ]
   ```

3. (optional) Add the console command for helper tasks to `console.commands` in your app's configuration:

   ```php
   'console' => [
	   // ...
	   'commands' => [
		   // ...
		   'App\Auth\Console\ResetPasswordLinkCommand'
	   ]
   ]
   ```

## Usage

You can create your own User model located at `App\Users\Models\User` for futher customization.