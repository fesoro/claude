# Networking

Backend developer üçün networking biliklərini sistemli şəkildə əhatə edən mövzular. OSI model-dən API security-yə, WebSocket-dən service discovery-yə qədər real layihələrdə tətbiq olunan konseptlər.

---

## Junior ⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [01-osi-model.md](01-osi-model.md) | OSI Model |
| 02 | [02-tcp-ip-model.md](02-tcp-ip-model.md) | TCP/IP Model |
| 03 | [03-tcp.md](03-tcp.md) | TCP Protocol |
| 04 | [04-udp.md](04-udp.md) | UDP Protocol |
| 05 | [05-http-protocol.md](05-http-protocol.md) | HTTP Protocol (1.0, 1.1, 2, 3) |
| 06 | [07-dns.md](07-dns.md) | DNS |
| 07 | [41-ip-addressing.md](41-ip-addressing.md) | IP Addressing for Backend Devs |
| 08 | [08-rest-api.md](08-rest-api.md) | REST API |
| 09 | [41-rest-best-practices.md](41-rest-best-practices.md) | REST Best Practices |
| 10 | [15-jwt.md](15-jwt.md) | JWT |
| 11 | [16-cors.md](16-cors.md) | CORS |
| 12 | [40-api-testing-tools.md](40-api-testing-tools.md) | API Testing Tools |

## Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 13 | [06-https-ssl-tls.md](06-https-ssl-tls.md) | HTTPS, SSL/TLS |
| 14 | [14-oauth2.md](14-oauth2.md) | OAuth 2.0 |
| 15 | [09-graphql.md](09-graphql.md) | GraphQL |
| 16 | [10-grpc.md](10-grpc.md) | gRPC |
| 17 | [11-websocket.md](11-websocket.md) | WebSocket |
| 18 | [12-sse.md](12-sse.md) | Server-Sent Events |
| 19 | [13-long-polling.md](13-long-polling.md) | Long Polling |
| 20 | [18-load-balancing.md](18-load-balancing.md) | Load Balancing |
| 21 | [19-reverse-proxy.md](19-reverse-proxy.md) | Reverse Proxy |
| 22 | [20-cdn.md](20-cdn.md) | CDN |
| 23 | [21-api-gateway.md](21-api-gateway.md) | API Gateway |
| 24 | [22-api-versioning.md](22-api-versioning.md) | API Versioning |
| 25 | [42-api-versioning.md](42-api-versioning.md) | API Versioning — Praktik |
| 26 | [23-webhooks.md](23-webhooks.md) | Webhooks |
| 27 | [44-webhook-design.md](44-webhook-design.md) | Webhook Design — Praktik |
| 28 | [24-api-pagination.md](24-api-pagination.md) | API Pagination |
| 29 | [47-api-pagination.md](47-api-pagination.md) | API Pagination — Praktik |
| 30 | [25-api-rate-limiting.md](25-api-rate-limiting.md) | API Rate Limiting |
| 31 | [27-email-protocols.md](27-email-protocols.md) | Email Protocols |
| 32 | [28-message-protocols.md](28-message-protocols.md) | Message Protocols (AMQP, MQTT, STOMP) |
| 33 | [38-openapi-swagger.md](38-openapi-swagger.md) | OpenAPI & Swagger |
| 34 | [39-protocol-buffers.md](39-protocol-buffers.md) | Protocol Buffers |
| 35 | [42-network-timeouts.md](42-network-timeouts.md) | Network Timeouts & Connection Management |
| 36 | [45-http-caching.md](45-http-caching.md) | HTTP Caching |
| 37 | [46-http-client-patterns.md](46-http-client-patterns.md) | HTTP Client Patterns |

## Senior ⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 38 | [17-api-security.md](17-api-security.md) | API Security |
| 39 | [26-network-security.md](26-network-security.md) | Network Security |
| 40 | [30-network-troubleshooting.md](30-network-troubleshooting.md) | Network Troubleshooting |
| 41 | [31-http3-quic.md](31-http3-quic.md) | HTTP/3 & QUIC |
| 42 | [32-webrtc.md](32-webrtc.md) | WebRTC |
| 43 | [33-zero-trust.md](33-zero-trust.md) | Zero Trust Security |
| 44 | [35-mtls-deep-dive.md](35-mtls-deep-dive.md) | mTLS Deep Dive |
| 45 | [43-service-discovery.md](43-service-discovery.md) | Service Discovery |
| 46 | [43-grpc-protobuf.md](43-grpc-protobuf.md) | gRPC & Protocol Buffers — Praktik |
| 47 | [48-websockets.md](48-websockets.md) | WebSockets — Praktik |
| 48 | [49-contract-first-api.md](49-contract-first-api.md) | Contract-First API Design |
| 49 | [50-realtime-communication.md](50-realtime-communication.md) | Real-time Communication Patterns |
| 50 | [51-email-delivery.md](51-email-delivery.md) | Email Delivery (SMTP, SPF, DKIM, DMARC) |

---

## Reading Paths

### API Developer (REST/GraphQL fokus)
`01-osi-model` → `05-http-protocol` → `08-rest-api` → `41-rest-best-practices` → `15-jwt` → `16-cors` → `14-oauth2` → `09-graphql` → `22-api-versioning` → `42-api-versioning` → `24-api-pagination` → `47-api-pagination` → `25-api-rate-limiting` → `17-api-security` → `38-openapi-swagger` → `49-contract-first-api`

### Real-time & Streaming
`05-http-protocol` → `11-websocket` → `48-websockets` → `12-sse` → `13-long-polling` → `10-grpc` → `43-grpc-protobuf` → `28-message-protocols` → `50-realtime-communication` → `32-webrtc`

### Infrastructure & Scaling
`41-ip-addressing` → `07-dns` → `18-load-balancing` → `19-reverse-proxy` → `20-cdn` → `21-api-gateway` → `42-network-timeouts` → `43-service-discovery` → `45-http-caching` → `46-http-client-patterns`

### Security fokus
`06-https-ssl-tls` → `15-jwt` → `14-oauth2` → `17-api-security` → `26-network-security` → `33-zero-trust` → `35-mtls-deep-dive`

### Protocol Deep Dive
`03-tcp` → `04-udp` → `05-http-protocol` → `10-grpc` → `39-protocol-buffers` → `43-grpc-protobuf` → `31-http3-quic`

### Webhook & Async Patterns
`23-webhooks` → `44-webhook-design` → `28-message-protocols` → `50-realtime-communication` → `51-email-delivery`
