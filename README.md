![Woody](woody_github_banner.jpg)

![PullRequest Welcome](https://img.shields.io/badge/PR-welcome-brightgreen.svg?style=flat-square)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/woody-wordpress/woody-cli.svg?style=flat-square)](https://php.net/releases/)
[![Latest Stable Version](https://img.shields.io/packagist/v/woody-wordpress/woody-cli.svg?style=flat-square)](https://packagist.org/packages/woody-wordpress/woody-cli)
![Required WP Version](https://img.shields.io/badge/wordpress->=4.8-blue.svg?style=flat-square)
![GitHub](https://img.shields.io/github/license/woody-wordpress/woody-cli.svg?style=flat-square)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/woody-wordpress/woody-cli.svg?style=flat-square&color=lightgrey)
[![Twitter Follow](https://img.shields.io/twitter/follow/raccourciagency.svg?label=Twitter&style=social)](https://twitter.com/raccourciagency)

* * *

A PHP CLI interface to deploy Woody website

## :fire: Installation

First, install Woody CLI via the Composer package manager:
```bash
composer require woody-wordpress/woody-cli
```

## :rocket: Usage

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

## :metal: Contributors

Thank you to all the people who have already contributed to Woody CLI !<br/>
For future contributors, please read our [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md)

Header photo by [John Lee on Unsplash](https://unsplash.com/@john_artifexfilms?utm_medium=referral&utm_campaign=photographer-credit&utm_content=creditBadge)<br/>
[![Header photo by John Lee on Unsplash](https://img.shields.io/badge/John%20Lee-black.svg?style=flat-square&logo=unsplash&logoWidth=10)](https://unsplash.com/@john_artifexfilms?utm_medium=referral&utm_campaign=photographer-credit&utm_content=creditBadge)

## :bookmark: License

Woody CLI is open-sourced software licensed under the [GPL2](LICENSE).

## :crown: Sponsoring

Woody is a digital ecosystem co-financed by the Regional Tourism Committee of Brittany for [eBreizh Connexion](http://www.ebreizhconnexion.bzh)

![eBreizh Connexion](logo_ebreizh_connexion.png)
