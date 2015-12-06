auth
=============

[![Build Status](https://travis-ci.org/infusephp/auth.png?branch=master)](https://travis-ci.org/infusephp/auth)
[![Coverage Status](https://coveralls.io/repos/infusephp/auth/badge.png)](https://coveralls.io/r/infusephp/auth)
[![Latest Stable Version](https://poser.pugx.org/infuse/auth/v/stable.png)](https://packagist.org/packages/infuse/auth)
[![Total Downloads](https://poser.pugx.org/infuse/auth/downloads.png)](https://packagist.org/packages/infuse/auth)
[![HHVM Status](http://hhvm.h4cc.de/badge/infuse/auth.svg)](http://hhvm.h4cc.de/package/infuse/auth)

Auth module for Infuse Framework

## Installation

1. Install the package with [composer](http://getcomposer.org):

```
composer require infuse/auth
```

2. (optional) Add the console command for helper tasks to `modules.commands` in your app's configuration:
```php
'modules' => [
	// ...
	'commands' => [
		// ...
		'App\Auth\Console\ResetPasswordLinkCommand'
	]
]
```

## Usage

You can create your own User model located at `App\Users\Models\User` for futher customization.