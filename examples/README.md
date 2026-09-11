# Examples

PHP examples for the Goldsky SDK. Each example reads credentials from
environment variables and makes a real API call (or shows the pattern for
doing so).

## Prerequisites

```bash
composer install
export GOLDSKY_API_KEY=your-project-bearer-token
export GOLDSKY_EDGE_API_KEY=your-edge-api-key    # for RPC examples
export PROJECT_ID=your-goldsky-project-id        # for GraphQL examples
```

## Examples

| #  | File                    | Description                                  |
|----|-------------------------|----------------------------------------------|
| 01 | `01-list-pipelines.php` | List a single page of pipelines               |
| 02 | `02-paginate-subgraphs.php` | Paginate all subgraphs using a pager     |
| 03 | `03-validate-pipeline.php` | Validate a pipeline definition              |
| 04 | `04-create-pipeline.php` | Create a pipeline                          |
| 05 | `05-graphql-query.php`  | Query a Subgraph GraphQL endpoint            |
| 06 | `06-edge-rpc.php`       | Call the Edge JSON-RPC endpoint (single + batch) |
| 07 | `07-verify-webhook.php` | Verify a webhook delivery signature          |
| 08 | `08-handle-errors.php`  | Handle and classify API errors               |

## Running

```bash
php examples/01-list-pipelines.php
```

## Laravel usage

In a Laravel application, use the facade instead of constructing the client
manually:

```php
use Tigusigalpa\Goldsky\Laravel\Facades\Goldsky;

$page = Goldsky::pipelines()->list(['page_size' => 50]);
```
