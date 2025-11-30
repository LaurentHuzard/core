# Linked Data Platform HTTP Headers

API Platform automatically exposes [Linked Data Platform](https://www.w3.org/TR/ldp/) compatible HTTP response headers on every REST endpoint.

## Response headers

* **`Allow`** lists all enabled HTTP methods for the requested IRI.
* **`Accept-Post`** lists the supported media types for POST requests on the current endpoint. It is only present when the endpoint exposes a `POST` operation.

The headers are computed from the resource metadata, so they always reflect the configured operations and input formats. For example:

```
Allow: GET, POST
Accept-Post: application/ld+json, text/turtle, application/json
```

## Configuration

The feature is enabled by default. You can disable it globally if you prefer to manage these headers yourself:

```yaml
# config/packages/api_platform.yaml
api_platform:
    ldp:
        enabled: false
```

With the default configuration the headers are added for all API Platform resources and keep your API compatible with LDP clients without any additional setup.
