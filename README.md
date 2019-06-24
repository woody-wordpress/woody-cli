## ✨ Features

A PHP CLI interface to deploy Woody website

## 👉🏻 Installation

> **Requires:**
- **[PHP 7.0+](https://php.net/releases/)**

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

## 🆓 License
Woody CLI is open-sourced software licensed under the [GPL2](LICENSE).
