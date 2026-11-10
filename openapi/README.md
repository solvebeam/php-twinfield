# Twinfield REST API (OpenAPI)

The Swagger 2.0 definitions of the Twinfield REST API, as the
[Swagger UI](https://api.accounting.twinfield.com/Api/swagger/ui/index) loads them. A daily GitHub Action
(`.github/workflows/sync-twinfield-openapi.yml`) downloads them, like the WSDL files in `wsdl/`.

| File | Source | Content |
|---|---|---|
| `{host}/open-v1.json` | `https://api.{host}.twinfield.com/Api/swagger/docs/open-v1` | Twinfield Public REST API Resources |
| `{host}/private.json` | `https://api.{host}.twinfield.com/Api/swagger/docs/private` | Twinfield Private REST API Resources |

The SDK only supports `open-v1`. `private` is kept to see when endpoints become public; it is internal and can
change without notice. The files of `accounting` and `accounting2` only differ in `host`.

All paths start with `/organisations/{organisationId}/companies/{companyId}`: the GUID of the organisation
(claim `twf.organisationId`) and the GUID of the company, not the company code. Twinfield returns the company
GUID with the Finder `OFF` and the `includeid` option (`OfficeSummary::$companyId` in the SDK).
