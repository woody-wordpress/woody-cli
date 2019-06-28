![Woody Website](woody_github_banner.jpg)

![PullRequest Welcome](https://img.shields.io/badge/PR-welcome-brightgreen.svg?style=flat-square)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/raccourci/woody-cli.svg?style=flat-square)](https://php.net/releases/)
[![Latest Stable Version](https://img.shields.io/packagist/v/raccourci/woody-cli.svg?style=flat-square)](https://packagist.org/packages/raccourci/woody-cli)
![Required WP Version](https://img.shields.io/badge/wordpress->=4.8-blue.svg?style=flat-square)
![GitHub](https://img.shields.io/github/license/raccourci/woody-cli.svg?style=flat-square)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/raccourci/woody-cli.svg?style=flat-square&color=lightgrey)
[![Twitter Follow](https://img.shields.io/twitter/follow/raccourciagency.svg?label=Twitter&style=social)](https://twitter.com/raccourciagency)

* * *

A PHP CLI interface to deploy Woody website

## 👉🏻 Installation

First, install Woody CLI via the Composer package manager:
```bash
composer require raccourci/woody-cli
```

## 🔥 Usage

> **Help:**

```bash
./bin/woody
```

> **Deploy Core:**

```bash
./bin/woody deploy:core
```

> **Deploy Site:**

```bash
./bin/woody deploy:site -s mywebsite -e prod
./bin/woody deploy:site --site=mywebsite --env=prod
```

This command includes options :

```bash
./bin/woody deploy:site -s mywebsite -o no-gulp,no-twig,no-varnish
```

- **no-gulp** : does not compile assets
- **no-twig** : does not empty the twig cache shared with all site instances
- **no-varnish** : does not start the emptying of the varnish cache of the site

> **Deploy Sites:**

```bash
./bin/woody deploy:sites
```

## 👏 Contributors

Thank you to all the people who have already contributed to Woody CLI !

For future contributors, please read our [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md)

## 🆓 License
Woody CLI is open-sourced software licensed under the [GPL2](LICENSE).
