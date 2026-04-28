## Basics

curl <url>                              — GET (default), body stdout-a
curl -o file.json <url>                 — fayla yaz
curl -O <url>                           — server-side adı saxla
curl -OJ <url>                          — Content-Disposition adını oxu
curl -L <url>                           — redirect-ləri izlə
curl -I <url>                           — HEAD only (status + headers)
curl -i <url>                           — body + headers
curl -s <url>                           — silent (progress yoxdur)
curl -sS <url>                          — silent ammada error göstər
curl -fsSL <url> | bash                 — fail+silent+show-error+follow (canonical)
curl -v <url>                           — verbose (request + response)
curl -vv / --trace-ascii -               — daha çox detay
curl --trace-ascii trace.log <url>      — fayla trace
curl --max-time 10 <url>                — total timeout (saniyə)
curl --connect-timeout 5 <url>          — connect-only timeout
curl -w "%{http_code} %{time_total}\n" -o /dev/null -s <url> — output template

## HTTP methods

curl -X GET <url>                                 — GET (explicit)
curl -X POST -d "k=v" <url>                       — POST form
curl -X PUT -d '{"x":1}' -H "Content-Type: application/json" <url>
curl -X PATCH ...
curl -X DELETE <url>
curl -X HEAD <url>                                — request without body (use -I instead)
curl -X OPTIONS <url>
curl --request-target "*" -X OPTIONS <url>        — server-wide OPTIONS

## Headers / authentication

curl -H "Accept: application/json" <url>
curl -H "X-Request-ID: abc-123" -H "X-Foo: bar" <url>
curl -H "Authorization: Bearer $TOKEN" <url>
curl -u user:pass <url>                           — Basic auth
curl --user user:pass <url>
curl -u user: <url>                               — interactive password
curl --digest -u user:pass <url>                  — Digest auth
curl --negotiate -u : <url>                       — Kerberos / SPNEGO
curl --ntlm -u user:pass <url>                    — NTLM
curl --aws-sigv4 "aws:amz:us-east-1:s3" -u "$KEY:$SECRET" <url>  — AWS Sig v4
curl -H "Cookie: session=abc" <url>
curl -A "Mozilla/5.0" <url>                       — User-Agent
curl -e "https://referer.com" <url>               — Referer
curl -H "Accept-Encoding: gzip" --compressed <url> — auto-decompress
curl -H "If-None-Match: \"etag\"" <url>           — conditional GET

## POST / request body

curl -X POST -d "name=alice&age=30" <url>                     — form url-encoded
curl -X POST --data-urlencode "q=hello world" <url>
curl -X POST --data-urlencode "q@search.txt" <url>            — fayldan
curl -X POST -d @body.json -H "Content-Type: application/json" <url>
curl -X POST --json '{"k":"v"}' <url>                          — 7.82+ shorthand
curl -X POST --json @body.json <url>
echo '{"k":"v"}' | curl -X POST -d @- -H "Content-Type: application/json" <url>
curl -X POST -F "file=@photo.jpg" -F "title=My pic" <url>     — multipart
curl -X POST -F "file=@photo.jpg;type=image/jpeg" <url>
curl -X POST -F "data=@payload.json;type=application/json" <url>
curl -X POST -F "[email protected]" <url>                       — content from file
curl -X POST --data-binary @big.bin -H "Content-Type: application/octet-stream" <url>
curl -X POST -T file.txt <url>                                — upload (PUT-like)

## File upload / download

curl -O <url>                                — adı serverdən
curl -o name.tar <url>
curl -L -o file <url>                        — redirect ilə
curl -C - -O <url>                           — resume (continue)
curl --range 0-1023 <url>                    — partial (Range header)
curl --limit-rate 100K <url>                 — bandwidth limit
curl -Z -O url1 -O url2 -O url3              — parallel (7.66+)
curl --parallel --parallel-max 5 -O url1 -O url2 ...

## Redirects / cookies

curl -L <url>                                — follow
curl --max-redirs 5 -L <url>                 — limit
curl -L --location-trusted <url>             — auth header redirect-də saxla
curl -c cookies.txt <url>                    — cookies yaz
curl -b cookies.txt <url>                    — cookies oxu
curl -b "session=abc; csrf=xyz" <url>        — inline
curl -b cookies.txt -c cookies.txt -L <url>  — session-li flow

## TLS / SSL / mTLS

curl -k <url>                                — insecure (skip cert verify) — PROD-DA QAÇIN
curl --cacert ca.pem <url>                   — custom CA bundle
curl --capath /etc/ssl/certs <url>
curl --cert client.pem --key client.key <url>  — mTLS PEM
curl --cert client.p12:pass --cert-type P12 <url>
curl --tlsv1.3 <url>                         — minimum TLS version
curl --tls-max 1.3 <url>
curl --ciphers 'TLS_AES_256_GCM_SHA384' <url>
curl --resolve example.com:443:1.2.3.4 https://example.com  — DNS override
curl --connect-to example.com:443:other:443 <url>
curl -v --servername sni.example.com <url>   — SNI override

# TLS handshake debug
openssl s_client -connect host:443 -servername host -showcerts

## HTTP/2 / HTTP/3

curl --http1.1 <url>
curl --http2 <url>                           — TLS-də auto-negotiate
curl --http2-prior-knowledge <url>           — h2c (no TLS upgrade)
curl --http3 <url>                           — QUIC (build dəstəkləməlidir)
curl -v --alt-svc <url>                      — Alt-Svc cache

## Proxy

curl -x http://proxy:8080 <url>              — HTTP proxy
curl --socks5 socks://proxy:1080 <url>
curl --socks5-hostname host:1080 <url>       — DNS via proxy
curl -x http://user:pass@proxy:8080 <url>
HTTPS_PROXY=http://proxy:8080 curl <url>     — env
curl --noproxy "*.local,localhost" <url>

## Inspection / diagnostics

curl -v <url>                                — request + response headers
curl --trace - <url>                         — bütün byte-lar
curl --trace-ascii - <url>                   — readable trace
curl --include / -i <url>                    — body + headers
curl -D headers.txt <url>                    — yalnız headers fayla
curl -D - <url>                              — headers stdout-a
curl -w "@format.txt" <url>                  — formatted vars
curl -w "code=%{http_code}\nrt=%{time_total}\nsize=%{size_download}\nip=%{remote_ip}\n" -o /dev/null -s <url>
curl --next                                  — bir komandada multiple requests

# Useful -w variables
%{http_code}        %{response_code}
%{time_namelookup}  DNS
%{time_connect}     TCP connect
%{time_appconnect}  TLS
%{time_starttransfer}  TTFB
%{time_total}       overall
%{size_download}    %{size_upload}
%{speed_download}
%{remote_ip}        %{remote_port}
%{ssl_verify_result}
%{num_redirects}
%{url_effective}    final URL after redirects

## WebSocket / SSE

curl --include --no-buffer \
  --header "Connection: Upgrade" \
  --header "Upgrade: websocket" \
  --header "Sec-WebSocket-Key: $(openssl rand -base64 16)" \
  --header "Sec-WebSocket-Version: 13" <url>      — WS handshake (curl --next-protocol websocket 7.86+)
curl --no-buffer -N <url>                          — SSE / streaming response

## Speed / repeated tests

curl -w "%{time_total}\n" -o /dev/null -s <url>
for i in {1..10}; do curl -w "%{time_total}\n" -o /dev/null -s <url>; done
curl --parallel --parallel-immediate -o f1 -o f2 -o f3 url1 url2 url3
hey / wrk / vegeta / k6   — proper load testing tools (curl deyil)

## Config / aliases

~/.curlrc                                  — default options
# Example .curlrc:
# silent
# show-error
# location
# user-agent = "MyAgent/1.0"
# referer = ";auto"

## HTTPie (alternativ — daha oxunaqlı CLI)

http GET https://api.example.com/users
http POST https://api.example.com/users name=alice age:=30
http -a user:pass https://api.example.com/profile
http :8000/users                            — localhost shortcut
http -f POST url field=value                — form
http --json POST url <<< '{"k":"v"}'
http -v / -h / -b                           — verbose / headers / body
http --download / -d url
http --session=mine url                     — saved session
http --offline POST url field=value         — diff "what would I send"
https GET ...                               — TLS shortcut

## Common patterns

# JSON pretty-print
curl -s url | jq '.'
curl -s url | jq -r '.items[].name'

# Save response + status
http_code=$(curl -s -o response.json -w "%{http_code}" url)

# Conditional download (ETag)
curl -z file.tar -o file.tar <url>          — only if newer (Last-Modified)
curl -H "If-None-Match: $(cat etag.txt)" <url>

# Retry on transient errors
curl --retry 3 --retry-delay 2 --retry-max-time 60 --retry-connrefused <url>
curl --retry 5 --retry-all-errors <url>     — 7.71+

# Bash bash sentinel: fail on HTTP errors
curl --fail-with-body <url>                 — 7.76+ (errors-də body göstər)
curl -fsSL <url>                            — fail silent show-error follow

# OAuth2 client credentials
TOKEN=$(curl -s -X POST -u "$CID:$CSEC" -d "grant_type=client_credentials" $TOKEN_URL | jq -r .access_token)
curl -H "Authorization: Bearer $TOKEN" $API_URL

# Webhook test (HMAC sign)
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$SECRET" -binary | base64)
curl -X POST -H "X-Signature: $SIG" -d "$BODY" <url>

# IPv4 / IPv6 force
curl -4 <url>
curl -6 <url>
curl --interface eth0 <url>

# Local debug → public via tunnel
ssh -R 80:localhost:8080 user@host        — reverse tunnel
ngrok http 8080                            — proxy service

## Common HTTP status codes (kontekst üçün)

1xx Informational    100 Continue, 101 Switching Protocols
2xx Success          200 OK, 201 Created, 202 Accepted, 204 No Content, 206 Partial Content
3xx Redirect         301 Moved Permanently, 302 Found, 304 Not Modified, 307 Temp Redirect, 308 Perm Redirect
4xx Client error     400 Bad Req, 401 Unauthorized, 403 Forbidden, 404 Not Found,
                     405 Method Not Allowed, 409 Conflict, 410 Gone, 422 Unprocessable,
                     425 Too Early, 428 Precondition Required, 429 Too Many Requests
5xx Server error     500 Internal, 501 Not Implemented, 502 Bad Gateway, 503 Service Unavailable,
                     504 Gateway Timeout, 507 Insufficient Storage
