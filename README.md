# AbraFlexi Contract to Invoices / Liabilities / Receivables

<p align="center">
 <img src="abraflexi-contract-invoices.svg?raw=true" alt="Invoices" height="100"/>
 <img src="abraflexi-contract-liabilities.svg?raw=true" alt="Liabilities" height="100"/>
 <img src="abraflexi-contract-receivables.svg?raw=true" alt="Receivables" height="100"/>
</p>

Trigger AbraFlexi contracts to generate Liabilities, Receivables or Invoices.

## Installation

```shell
sudo apt install lsb-release wget
echo "deb http://repo.vitexsoftware.com $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
sudo wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.cz/keyring.gpg
sudo apt update
sudo apt install abraflexi-contract-invoices
```

See also <https://github.com/VitexSoftware/MultiAbraFlexiSetup>

## Configuration

You can put configuration into .env file in current directory
Command try to use standard configuration keys:

```env
EASE_LOGGER=console|syslog

ABRAFLEXI_LOGIN=winstrom
ABRAFLEXI_PASSWORD=winstrom
ABRAFLEXI_URL=https://demo.abraflexi.eu:5434
ABRAFLEXI_COMPANY=demo_de
```

We use environment variables as described here: <https://github.com/Spoje-NET/php-abraflexi>

## MultiFlexi

**AbraFlexi Contract to Invoices** is ready for run as [MultiFlexi](https://multiflexi.eu) application.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg?raw=true)](https://www.multiflexi.eu/apps.php)

## JSON Output

All scripts now generate schema-compliant JSON reports according to the [MultiFlexi Application Report Schema](https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/schema/report.json).

The JSON output includes:

- **`producer`** (required): Name of the script that generated the report
- **`status`** (required): Execution result (`success`, `error`, or `warning`)
- **`timestamp`** (required): ISO8601 formatted completion time
- **`message`** (optional): Human-readable execution result message
- **`artifacts`** (optional): Generated outputs (invoices, liabilities, receivables)
- **`metrics`** (optional): Execution metrics (processed items, success/failure counts)

### Example Output

```json
{
  "producer": "AbraFlexi Contracts2Invoices",
  "status": "success",
  "timestamp": "2026-02-02T17:29:27+00:00",
  "message": "Invoice generation completed",
  "artifacts": {
    "invoices": {
      "CONTRACT001": "Test Invoice 1",
      "CONTRACT002": "Test Invoice 2"
    }
  },
  "metrics": {
    "processed_contracts": 2,
    "created_invoices": 2,
    "failed_contracts": 0
  }
}
```

### Output Redirection

Use the `--output` (or `-o`) parameter to redirect JSON output to a file:

```bash
abraflexi-contract-invoices --output=/path/to/output.json
```
