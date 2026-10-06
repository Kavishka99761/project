# HTTP, REST and Web APIs

Modern web applications are split into a front end that runs in the browser and a back end that exposes data and operations through an application programming interface (API). Most web APIs are built on HTTP and follow the REST architectural style.

## The HTTP protocol

HTTP is a stateless request-response protocol used by browsers and servers to exchange resources. A request contains a method, a URL, headers and an optional body. The response contains a status code, headers and usually a body in HTML or JSON. Because HTTP is stateless, every request must carry the information the server needs, such as an authentication token in the Authorization header.

## HTTP methods

GET retrieves a resource and must not change data on the server. POST creates a new resource or triggers processing. PUT replaces a resource, while PATCH applies a partial update. DELETE removes a resource. GET, PUT and DELETE are idempotent, which means sending the same request several times has the same effect as sending it once.

## Status codes

Status codes tell the client what happened. Codes in the 200 range indicate success, such as 200 OK and 201 Created. Codes in the 400 range indicate client errors: 400 Bad Request, 401 Unauthorized when authentication is missing, 403 Forbidden when the user is not allowed, 404 Not Found and 422 Unprocessable Content for validation errors. Codes in the 500 range indicate server errors that the client cannot fix.

## REST principles

REST stands for Representational State Transfer and was described by Roy Fielding in his doctoral dissertation. A RESTful API models the domain as resources identified by URLs, such as /api/v1/assignments/42. Clients manipulate resources through representations, usually JSON documents. REST constraints include a uniform interface, statelessness, cacheability and a layered system. Good REST APIs use nouns in URLs, plural resource names and HTTP methods for actions.

## API design practices

Versioning the API, for example with a /v1 prefix, allows the server to evolve without breaking existing clients. Pagination limits the size of large collections and returns links or metadata for the next page. Consistent error responses with a message and field-level validation errors make APIs easier to consume. Authentication is commonly implemented with bearer tokens, and rate limiting protects the API from abuse.

## Cross-origin requests

Browsers enforce the same-origin policy, which blocks scripts from reading responses from a different origin. Cross-Origin Resource Sharing (CORS) lets a server explicitly allow trusted origins through response headers such as Access-Control-Allow-Origin. Before sending certain requests, the browser performs a preflight OPTIONS request to check whether the server permits the method and headers.
