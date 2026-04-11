docker build -t <name>:<tag> . — image yarat
docker images — local image-ləri listele
docker rmi <image> — image-i sil
docker pull <image> — registry-dən image çək
docker push <image> — image-i registry-ə göndər
docker run -d -p 8080:80 --name <name> <image> — container işə sal
docker run -v /host:/container <image> — volume mount et
docker run -e KEY=VALUE <image> — env variable ilə işə sal
docker run --rm <image> — bitdikdə avtomatik sil
docker ps — işləyən container-ləri göstər
docker ps -a — bütün container-ləri göstər
docker stop <container> — container-i dayandır
docker rm <container> — container-i sil
docker rm -f <container> — işləyən container-i məcburi sil
docker exec -it <container> bash — container-in içinə gir
docker logs -f <container> — log-ları canlı izlə
docker inspect <container> — container detallarını göstər
docker stats — resurs istifadəsini göstər
docker cp <src> <container>:<dst> — fayl kopyala
docker volume create <name> — volume yarat
docker volume ls — volume-ları listele
docker network create <name> — network yarat
docker network ls — network-ləri listele
docker system prune -a — bütün istifadəsiz resursları təmizlə
docker compose up -d — servisləri arxa planda işə sal
docker compose down — servisləri dayandır və sil
docker compose build — image-ləri yenidən qur
docker compose logs -f — bütün servislərin log-larını izlə
docker compose ps — servislərin vəziyyəti
docker compose exec <service> bash — servisə daxil ol
