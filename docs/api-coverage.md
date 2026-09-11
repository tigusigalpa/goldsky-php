# API Coverage

This document maps every operation in the Goldsky REST API endpoint manifest to
its public PHP method, request array shape, response model, contract test, and
exact documentation URL.

## OpenAPI snapshot

| Field | Value |
| --- | --- |
| Source | <https://api.goldsky.com/api/v1/docs/openapi.json> |
| `openapi` | `3.1.0` |
| `info.title` | Goldsky API |
| `info.version` | `1.2.0` |
| Operation count | 40 |
| Snapshot date | 2026-09-08 |
| Local fixture | `testdata/openapi/openapi.json` (from goldsky-go) |

The live OpenAPI document is the primary contract. The examined reference
version is Goldsky REST v1.2.0 with 40 operations. At build time the live spec
was fetched and diffed against the manifest below; **no delta was found** — all
40 operations, paths, and verbs match.

### Updating the snapshot safely

1. Download the live spec:
   `curl -sSL https://api.goldsky.com/api/v1/docs/openapi.json -o testdata/openapi/openapi.json`
2. Record the new `info.version` and fetch date in the table above.
3. Compare the operation list against the manifest below. If the spec changed,
   implement the live spec, update this file and the contract tests, and report
   the delta in `CHANGELOG.md`.
4. Run `composer test` and ensure the contract suite still passes.

## Endpoint manifest

The 40 links below are concrete operation pages in the interactive Goldsky API
reference. The companion machine-readable source is
<https://api.goldsky.com/api/v1/docs/openapi.json>.

### Turbo Pipelines

| # | Capability | Method | Path | PHP method | Request array | Response | Test | Docs |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | List pipelines | GET | `/pipelines` | `$client->pipelines->list()` | `ListPipelinesOptions` | `Page` | `ContractTest::testListPipelines` | <https://api.goldsky.com/api/v1/docs#tag/Pipelines/operation/listPipelines> |
| 2 | Create pipeline | POST | `/pipelines` | `$client->pipelines->create()` | `CreatePipelineRequest` | `array` | `ContractTest::testCreatePipeline` | <https://api.goldsky.com/api/v1/docs#tag/Pipelines/operation/createPipeline> |
| 3 | Get pipeline | GET | `/pipelines/{name}` | `$client->pipelines->get()` | (name) | `array` | `ContractTest::testGetPipeline` | <https://api.goldsky.com/api/v1/docs#tag/Pipelines/operation/getPipeline> |
| 4 | Delete pipeline | DELETE | `/pipelines/{name}` | `$client->pipelines->delete()` | (name) | void | `ContractTest::testDeletePipeline` | <https://api.goldsky.com/api/v1/docs#tag/Pipelines/operation/deletePipeline> |
| 5 | Validate pipeline | POST | `/pipelines/validate` | `$client->pipelines->validate()` | `ValidatePipelineRequest` | `array` | `ContractTest::testValidatePipeline` | <https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Authoring/operation/validatePipeline> |
| 6 | Preview pipeline | POST | `/pipelines/preview` | `$client->pipelines->preview()` | `PreviewPipelineRequest` | `array` | `ContractTest::testPreviewPipeline` | <https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Authoring/operation/previewPipeline> |
| 7 | Pause pipeline | PUT | `/pipelines/{name}/pause` | `$client->pipelines->pause()` | (name) | void | `ContractTest::testPausePipeline` | <https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Lifecycle/operation/pausePipeline> |
| 8 | Resume pipeline | PUT | `/pipelines/{name}/resume` | `$client->pipelines->resume()` | (name) | void | `ContractTest::testResumePipeline` | <https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Lifecycle/operation/resumePipeline> |
| 9 | Restart pipeline | PUT | `/pipelines/{name}/restart` | `$client->pipelines->restart()` | `RestartPipelineRequest` | void | `ContractTest::testRestartPipeline` | <https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Lifecycle/operation/restartPipeline> |
| 10 | Get pipeline logs | GET | `/pipelines/{name}/logs` | `$client->pipelines->logs()` | `PipelineLogsOptions` | `array` | `ContractTest::testGetPipelineLogs` | <https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Logs/operation/getPipelineLogs> |
| 11 | Get pipeline error count | GET | `/pipelines/{name}/logs/error-count` | `$client->pipelines->errorCount()` | (name, sinceHours) | `array` | `ContractTest::testGetPipelineErrorCount` | <https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Logs/operation/getPipelineErrorCount> |
| 12 | Get pipeline status | GET | `/pipelines/{name}/status` | `$client->pipelines->status()` | (name) | `array` | `ContractTest::testGetPipelineStatus` | <https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Status/operation/getPipelineStatus> |
| 13 | Get pipeline state | GET | `/pipelines/{name}/state` | `$client->pipelines->state()` | (name) | `array` | `ContractTest::testGetPipelineState` | <https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Status/operation/getPipelineState> |

### Subgraphs and Subgraph Webhooks

| # | Capability | Method | Path | PHP method | Request array | Response | Test | Docs |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 14 | List subgraphs | GET | `/subgraphs` | `$client->subgraphs->list()` | `ListSubgraphsOptions` | `Page` | `ContractTest::testListSubgraphs` | <https://api.goldsky.com/api/v1/docs#tag/Subgraphs/operation/listSubgraphs> |
| 15 | Get subgraph | GET | `/subgraphs/{name}` | `$client->subgraphs->get()` | (name) | `Page` | `ContractTest::testGetSubgraph` | <https://api.goldsky.com/api/v1/docs#tag/Subgraphs/operation/getSubgraph> |
| 16 | List supported chains | GET | `/subgraphs/supported-chains` | `$client->subgraphs->supportedChains()` / `$client->catalogs->supportedSubgraphChains()` | — | `array` | `ContractTest::testListSubgraphChains` | <https://api.goldsky.com/api/v1/docs#tag/Catalogs/operation/listSubgraphChains> |
| 17 | Get subgraph version | GET | `/subgraphs/{name}/{version}` | `$client->subgraphs->getVersion()` | (name, version) | `Page` | `ContractTest::testGetSubgraphVersion` | <https://api.goldsky.com/api/v1/docs#tag/Subgraphs/operation/getSubgraphVersion> |
| 18 | Update version | PATCH | `/subgraphs/{name}/{version}` | `$client->subgraphs->updateVersion()` | `UpdateSubgraphVersionRequest` | `array` | `ContractTest::testUpdateSubgraphVersion` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Lifecycle/operation/updateSubgraphVersion> |
| 19 | Get subgraph logs | GET | `/subgraphs/{name}/{version}/logs` | `$client->subgraphs->logs()` | `SubgraphLogsOptions` | `array` | `ContractTest::testGetSubgraphLogs` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Logs/operation/getSubgraphLogs> |
| 20 | Pause subgraph | PUT | `/subgraphs/{name}/{version}/pause` | `$client->subgraphs->pause()` | (name, version) | void | `ContractTest::testPauseSubgraph` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Lifecycle/operation/pauseSubgraph> |
| 21 | Resume subgraph | PUT | `/subgraphs/{name}/{version}/resume` | `$client->subgraphs->resume()` | (name, version) | void | `ContractTest::testResumeSubgraph` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Lifecycle/operation/resumeSubgraph> |
| 22 | Set tag | PUT | `/subgraphs/{name}/tags/{version}` | `$client->subgraphs->setTag()` | `SetSubgraphTagRequest` | `array` | `ContractTest::testSetSubgraphTag` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Tags/operation/setSubgraphTag> |
| 23 | Delete tag | DELETE | `/subgraphs/{name}/tags/{version}` | `$client->subgraphs->deleteTag()` | (name, version) | void | `ContractTest::testDeleteSubgraphTag` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Tags/operation/deleteSubgraphTag> |
| 24 | Delete deployment | DELETE | `/subgraphs/{name}/deployments/{version}` | `$client->subgraphs->deleteDeployment()` | (name, version) | void | `ContractTest::testDeleteSubgraphDeployment` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Deployments/operation/deleteSubgraphDeployment> |
| 25 | Deploy subgraph | PUT | `/subgraphs/{name}/deployments/{version}` | `$client->subgraphs->deploy()` | `DeploySubgraphOptions` | `array` | `ContractTest::testDeploySubgraph` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Deployments/operation/deploySubgraph> |
| 26 | List webhooks | GET | `/subgraphs/webhooks` | `$client->webhooks->list()` | — | `array` | `ContractTest::testListWebhooks` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Webhooks/operation/listWebhooks> |
| 27 | Create webhook | POST | `/subgraphs/webhooks` | `$client->webhooks->create()` | `CreateWebhookRequest` | `array` | `ContractTest::testCreateWebhook` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Webhooks/operation/createWebhook> |
| 28 | Delete webhook | DELETE | `/subgraphs/webhooks/{name}` | `$client->webhooks->delete()` | (name) | void | `ContractTest::testDeleteWebhook` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Webhooks/operation/deleteWebhook> |
| 29 | List webhook entities | GET | `/subgraphs/{name}/{version}/entities` | `$client->subgraphs->webhookEntities()` | (name, version) | `array` | `ContractTest::testListWebhookEntities` | <https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Webhooks/operation/listWebhookEntities> |

### Edge endpoints and catalogs

| # | Capability | Method | Path | PHP method | Request array | Response | Test | Docs |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 30 | List Edge networks | GET | `/edge/networks` | `$client->catalogs->edgeNetworks()` | — | `array` | `ContractTest::testListEdgeNetworks` | <https://api.goldsky.com/api/v1/docs#tag/Catalogs/operation/listEdgeNetworks> |
| 31 | List Edge sources | GET | `/edge/sources` | `$client->catalogs->edgeSources()` | — | `array` | `ContractTest::testListEdgeSources` | <https://api.goldsky.com/api/v1/docs#tag/Catalogs/operation/listEdgeSources> |
| 32 | List Edge endpoints | GET | `/edge` | `$client->edge->list()` | `ListEdgeEndpointsOptions` | `Page` | `ContractTest::testListEdgeEndpoints` | <https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints/operation/listEdgeEndpoints> |
| 33 | Create Edge endpoint | POST | `/edge` | `$client->edge->create()` | `CreateEdgeEndpointRequest` | `array` | `ContractTest::testCreateEdgeEndpoint` | <https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints/operation/createEdgeEndpoint> |
| 34 | Get Edge endpoint | GET | `/edge/{name}` | `$client->edge->get()` | (name) | `array` | `ContractTest::testGetEdgeEndpoint` | <https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints/operation/getEdgeEndpoint> |
| 35 | Update Edge endpoint | PATCH | `/edge/{name}` | `$client->edge->update()` | `UpdateEdgeEndpointRequest` | `array` | `ContractTest::testUpdateEdgeEndpoint` | <https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints/operation/updateEdgeEndpoint> |
| 36 | Delete Edge endpoint | DELETE | `/edge/{name}` | `$client->edge->delete()` | (name) | void | `ContractTest::testDeleteEdgeEndpoint` | <https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints/operation/deleteEdgeEndpoint> |
| 37 | Pause Edge endpoint | PUT | `/edge/{name}/pause` | `$client->edge->pause()` | (name) | `array` | `ContractTest::testPauseEdgeEndpoint` | <https://api.goldsky.com/api/v1/docs#tag/Edge%20Lifecycle/operation/pauseEdgeEndpoint> |
| 38 | Resume Edge endpoint | PUT | `/edge/{name}/resume` | `$client->edge->resume()` | (name) | `array` | `ContractTest::testResumeEdgeEndpoint` | <https://api.goldsky.com/api/v1/docs#tag/Edge%20Lifecycle/operation/resumeEdgeEndpoint> |
| 39 | Reveal Edge API key | GET | `/edge/{name}/api-key` | `$client->edge->revealKey()` | (name) | `array` | `ContractTest::testRevealEdgeEndpointKey` | <https://api.goldsky.com/api/v1/docs#tag/Edge%20API%20Keys/operation/revealEdgeEndpointKey> |
| 40 | Get Edge metrics | GET | `/edge/{name}/metrics` | `$client->edge->metrics()` | `EdgeMetricsOptions` | `array` | `ContractTest::testGetEdgeEndpointMetrics` | <https://api.goldsky.com/api/v1/docs#tag/Edge%20Metrics/operation/getEdgeEndpointMetrics> |

## Data planes (outside the REST manifest)

| Capability | PHP method | Docs |
| --- | --- | --- |
| GraphQL Subgraph query (public/private) | `$client->graphQL->query()`, `queryPublic()`, `queryPrivate()` | <https://docs.goldsky.com/subgraphs/graphql-endpoints> |
| Edge JSON-RPC (single/batch) | `$client->rpc->call()`, `$client->rpc->batch()` | <https://docs.goldsky.com/edge-rpc/quickstart> |
| Webhook secret verification | `WebhookVerifier::verifySecret()`, `verifyRequest()` | <https://docs.goldsky.com/subgraphs/webhooks> |

## Pagination

`Pipelines::list()`, `Subgraphs::list()`, and `Edge::list()` return a `Page`
with a `pagination.next_page_token`. Use `Pipelines::newPager()`,
`Subgraphs::newPager()`, or `Edge::newPager()` for full iteration. A page can
hold fewer than `page_size` items and still have a next page, so completion is
inferred from `next_page_token` alone.
