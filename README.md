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

## 👉🏻 Installation

First, install Woody CLI via the Composer package manager:
```bash
composer require woody-wordpress/woody-cli
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

Header photo by John Lee on Unsplash <a style="background-color:black;color:white;text-decoration:none;padding:4px 6px;font-family:-apple-system, BlinkMacSystemFont, &quot;San Francisco&quot;, &quot;Helvetica Neue&quot;, Helvetica, Ubuntu, Roboto, Noto, &quot;Segoe UI&quot;, Arial, sans-serif;font-size:12px;font-weight:bold;line-height:1.2;display:inline-block;border-radius:3px" href="https://unsplash.com/@john_artifexfilms?utm_medium=referral&amp;utm_campaign=photographer-credit&amp;utm_content=creditBadge" target="_blank" rel="noopener noreferrer" title="Download free do whatever you want high-resolution photos from John Lee"><span style="display:inline-block;padding:2px 3px"><svg xmlns="http://www.w3.org/2000/svg" style="height:12px;width:auto;position:relative;vertical-align:middle;top:-2px;fill:white" viewBox="0 0 32 32"><title>unsplash-logo</title><path d="M10 9V0h12v9H10zm12 5h10v18H0V14h10v9h12v-9z"></path></svg></span><span style="display:inline-block;padding:2px 3px">John Lee</span></a>

## 🆓 License

Woody CLI is open-sourced software licensed under the [GPL2](LICENSE).

## 💝 Sponsoring

Woody is a digital ecosystem co-financed by the Regional Tourism Committee of Brittany for [eBreizh Connexion](http://www.ebreizhconnexion.bzh)

![eBreizh Connexion](logo_ebreizh_connexion.png)
