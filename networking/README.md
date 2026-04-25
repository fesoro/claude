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
| 09 | [15-jwt.md](15-jwt.md) | JWT |
| 10 | [16-cors.md](16-cors.md) | CORS |
| 11 | [40-api-testing-tools.md](40-api-testing-tools.md) | API Testing Tools |

## Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 12 | [06-https-ssl-tls.md](06-https-ssl-tls.md) | HTTPS, SSL/TLS |
| 13 | [14-oauth2.md](14-oauth2.md) | OAuth 2.0 |
| 14 | [09-graphql.md](09-graphql.md) | GraphQL |
| 15 | [10-grpc.md](10-grpc.md) | gRPC |
| 16 | [11-websocket.md](11-websocket.md) | WebSocket |
| 17 | [12-sse.md](12-sse.md) | Server-Sent Events |
| 18 | [13-long-polling.md](13-long-polling.md) | Long Polling |
| 19 | [18-load-balancing.md](18-load-balancing.md) | Load Balancing |
| 20 | [19-reverse-proxy.md](19-reverse-proxy.md) | Reverse Proxy |
| 21 | [20-cdn.md](20-cdn.md) | CDN |
| 22 | [21-api-gateway.md](21-api-gateway.md) | API Gateway |
| 23 | [22-api-versioning.md](22-api-versioning.md) | API Versioning |
| 24 | [23-webhooks.md](23-webhooks.md) | Webhooks |
| 25 | [24-api-pagination.md](24-api-pagination.md) | API Pagination |
| 26 | [25-api-rate-limiting.md](25-api-rate-limiting.md) | API Rate Limiting |
| 27 | [27-email-protocols.md](27-email-protocols.md) | Email Protocols |
| 28 | [28-message-protocols.md](28-message-protocols.md) | Message Protocols (AMQP, MQTT, STOMP) |
| 29 | [38-openapi-swagger.md](38-openapi-swagger.md) | OpenAPI & Swagger |
| 30 | [39-protocol-buffers.md](39-protocol-buffers.md) | Protocol Buffers |
| 31 | [42-network-timeouts.md](42-network-timeouts.md) | Network Timeouts & Connection Management |

## Senior ⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 32 | [17-api-security.md](17-api-security.md) | API Security |
| 33 | [26-network-security.md](26-network-security.md) | Network Security |
| 34 | [30-network-troubleshooting.md](30-network-troubleshooting.md) | Network Troubleshooting |
| 35 | [31-http3-quic.md](31-http3-quic.md) | HTTP/3 & QUIC |
| 36 | [32-webrtc.md](32-webrtc.md) | WebRTC |
| 37 | [33-zero-trust.md](33-zero-trust.md) | Zero Trust Security |
| 38 | [35-mtls-deep-dive.md](35-mtls-deep-dive.md) | mTLS Deep Dive |
| 39 | [43-service-discovery.md](43-service-discovery.md) | Service Discovery |

---

## Reading Paths

### API Developer (REST/GraphQL fokus)
`01-osi-model` → `05-http-protocol` → `08-rest-api` → `15-jwt` → `16-cors` → `14-oauth2` → `09-graphql` → `22-api-versioning` → `24-api-pagination` → `25-api-rate-limiting` → `17-api-security` → `38-openapi-swagger`

### Real-time & Streaming
`05-http-protocol` → `11-websocket` → `12-sse` → `13-long-polling` → `10-grpc` → `28-message-protocols` → `32-webrtc`

### Infrastructure & Scaling
`41-ip-addressing` → `07-dns` → `18-load-balancing` → `19-reverse-proxy` → `20-cdn` → `21-api-gateway` → `42-network-timeouts` → `43-service-discovery`

### Security fokus
`06-https-ssl-tls` → `15-jwt` → `14-oauth2` → `17-api-security` → `26-network-security` → `33-zero-trust` → `35-mtls-deep-dive`

### Protocol Deep Dive
`03-tcp` → `04-udp` → `05-http-protocol` → `10-grpc` → `39-protocol-buffers` → `31-http3-quic`
