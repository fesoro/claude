## Image commands

docker build -t <name>:<tag> . — image yarat
docker build --no-cache -t <name>:<tag> . — cache istifadə etmə
docker build --target builder -t <name> . — multi-stage üçün konkret stage
docker build --platform linux/amd64,linux/arm64 -t <name> . — multi-platform
docker buildx build --push --platform linux/amd64,linux/arm64 -t <name> . — push ilə
docker images — local image-ləri listele
docker images -a — hər şey (intermediate daxil)
docker rmi <image> — image-i sil
docker rmi $(docker images -q) — bütün image-ları sil (diqqətli!)
docker image prune — dangling image-ləri sil
docker image prune -a — istifadə edilməyən bütün image-lar
docker pull <image>:<tag> — registry-dən image çək
docker push <image>:<tag> — image-i registry-ə göndər
docker tag <src> <dst> — image-ə yeni tag ver
docker history <image> — image layer-lərini göstər
docker save -o image.tar <image> — image-i fayla yaz
docker load -i image.tar — fayldan image oxu
docker image inspect <image> — image detalları
docker scan <image> — vulnerability scan
docker scout cves <image> — modern CVE scan (Docker Scout)

## Container lifecycle

docker run <image> — container yarat və işə sal
docker run -d <image> — detached (arxa planda)
docker run -it <image> bash — interactive + tty
docker run --rm <image> — bitdikdə avtomatik sil
docker run --name <name> <image> — container adı ver
docker run -p 8080:80 <image> — port forward (host:container)
docker run -p 127.0.0.1:8080:80 <image> — yalnız localhost-a bind
docker run -v /host:/container <image> — bind mount
docker run -v myvol:/data <image> — named volume
docker run -e KEY=VALUE <image> — env variable
docker run --env-file .env <image> — env faylından
docker run --network mynet <image> — network seç
docker run --restart=unless-stopped <image> — restart policy
docker run --memory="512m" --cpus="1.0" <image> — resource limit
docker run -u 1000:1000 <image> — user ID
docker run --read-only <image> — read-only filesystem
docker run --cap-drop=ALL --cap-add=NET_BIND_SERVICE <image> — capabilities
docker run --security-opt no-new-privileges <image> — privilege escalation disable
docker run --tmpfs /tmp <image> — tmpfs mount
docker create <image> — yarat amma işə salma
docker start <container> — dayandırılmış container-i işə sal
docker stop <container> — SIGTERM göndər (10s timeout)
docker stop -t 30 <container> — 30s timeout
docker kill <container> — SIGKILL
docker restart <container> — restart
docker pause/unpause <container> — SIGSTOP/SIGCONT
docker rm <container> — container-i sil
docker rm -f <container> — işləyən container-i məcburi sil
docker rm $(docker ps -aq) — bütün container-ləri sil
docker container prune — dayandırılmış container-ləri təmizlə

## Inspect / debug

docker ps — işləyən container-ləri göstər
docker ps -a — bütün container-ləri (dayandırılmış daxil)
docker ps --format "{{.Names}}\t{{.Status}}" — custom format
docker ps --filter "status=exited" --filter "ancestor=nginx"
docker logs <container> — log-ları göstər
docker logs -f <container> — follow mode
docker logs --tail 100 <container> — son 100 sətir
docker logs --since 10m <container> — son 10 dəqiqə
docker exec -it <container> bash — container-in içinə gir
docker exec <container> ls /app — tək komanda işlət
docker inspect <container> — container detallarını göstər (JSON)
docker inspect -f '{{.State.Status}}' <container> — template ilə
docker stats — resurs istifadəsini real-time göstər
docker stats --no-stream — bir dəfə göstər
docker top <container> — container-in process-lərini göstər
docker port <container> — port mapping-ləri göstər
docker diff <container> — filesystem dəyişiklikləri
docker cp <src> <container>:<dst> — fayl kopyala (container-ə)
docker cp <container>:<src> <dst> — fayl kopyala (container-dən)
docker attach <container> — container-in stdin/stdout-a bağla
docker wait <container> — container bitənə qədər gözlə
docker commit <container> <image> — container-dən image yarat

## Volume

docker volume create <name> — volume yarat
docker volume ls — volume-ları listele
docker volume inspect <name> — volume detalları
docker volume rm <name> — volume sil
docker volume prune — istifadəsiz volume-ları sil

## Network

docker network create <name> — network yarat
docker network create --driver overlay <name> — swarm üçün
docker network ls — network-ləri listele
docker network inspect <name> — network detalları
docker network connect <net> <container> — qoş
docker network disconnect <net> <container> — çıxar
docker network rm <name> — sil
docker network prune — istifadəsiz network-ləri sil

## Registry / auth

docker login — default registry-ə login
docker login ghcr.io -u <user> — GitHub registry
docker logout [registry]
docker search <term> — Docker Hub axtarışı

## Cleanup

docker system df — disk istifadəsi
docker system prune — dangling resources
docker system prune -a — istifadə edilməyən hər şey
docker system prune -a --volumes — volume-ları daxil
docker system events — real-time event stream

## Docker Compose

docker compose up — servisləri işə sal (yeni CLI)
docker compose up -d — detached
docker compose up --build — build ilə
docker compose up -d --scale worker=3 — scaling
docker compose down — dayandır və sil
docker compose down -v — volume-ları da sil
docker compose down --rmi all — image-ları da sil
docker compose build — build image-ları
docker compose build --no-cache
docker compose pull — image-ları çək
docker compose logs -f — bütün servislərin log-ları
docker compose logs -f web — konkret servis
docker compose ps — servislərin vəziyyəti
docker compose exec <service> bash — servisə daxil ol
docker compose run --rm <service> <cmd> — tək komanda işlət
docker compose restart [service]
docker compose stop/start [service]
docker compose config — compose.yaml-ı validate et və göstər
docker compose top — hər servisin process-ləri
docker compose -f prod.yaml up — custom fayl

## Buildx / BuildKit

docker buildx create --name multi — builder yarat
docker buildx ls — builder-ləri göstər
docker buildx use <name> — builder seç
docker buildx inspect --bootstrap
docker buildx build --cache-from type=registry,ref=... — remote cache
docker buildx bake — HCL-based multi-target build
DOCKER_BUILDKIT=1 docker build . — BuildKit aktivləşdir

## Swarm (əgər istifadə edirsənsə)

docker swarm init — swarm cluster başlat
docker swarm join --token <token> <ip>:<port>
docker service create --name web --replicas 3 nginx
docker service ls
docker service scale web=5
docker stack deploy -c compose.yaml myapp
docker node ls
