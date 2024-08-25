# abraflexi-contract-invoices

![app logo](abraflexi-contract-invoices.svg?raw=true)

Trigger AbraFlexi contracts to generate invoices



Installation
------------

```shell
sudo apt install lsb-release wget
echo "deb http://repo.vitexsoftware.com $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
sudo wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.cz/keyring.gpg
sudo apt update
sudo apt install abraflexi-contract-invoices
```

See also https://github.com/VitexSoftware/MultiAbraFlexiSetup


Configuration
-------------

You can put configuration into .env file in current directory
Command try to use standard configuration keys:

```
EASE_LOGGER=console|syslog

ABRAFLEXI_LOGIN=winstrom
ABRAFLEXI_PASSWORD=winstrom
ABRAFLEXI_URL=https://demo.abraflexi.eu:5434
ABRAFLEXI_COMPANY=demo_de
```

We use environment variables as described here: https://github.com/Spoje-NET/php-abraflexi

MultiFlexi
----------

**AbraFlexi Contract to Invoices** is ready for run as [MultiFlexi](https://multiflexi.eu) application.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/apps.php)
