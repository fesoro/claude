ls -la — gizli fayllarla detallı listele
pwd — cari direktoriya
cd <dir> — direktoriyaya keç
mkdir -p <path> — iç-içə direktoriya yarat
rm -rf <dir> — direktoriya və məzmununu sil
cp -r <src> <dst> — rekursiv kopyala
mv <src> <dst> — köçür / adını dəyiş
touch <file> — boş fayl yarat
cat <file> — fayl məzmununu göstər
less <file> — səhifə-səhifə göstər
head -n <n> <file> — ilk n sətir
tail -n <n> <file> — son n sətir
tail -f <file> — faylı canlı izlə
grep -r "pattern" <dir> — rekursiv axtarış
grep -i "pattern" <file> — case-insensitive axtarış
find <dir> -name "*.txt" — fayl tap
chmod +x <file> — icra icazəsi ver
chmod 755 <file> — icazələri dəyiş
chown user:group <file> — sahibi dəyiş
ps aux — bütün proseslər
ps aux | grep <name> — proses axtar
kill -9 <pid> — prosesi məcburi dayandır
top / htop — resurs istifadəsi
df -h — disk istifadəsi
du -sh <dir> — qovluq ölçüsü
free -h — RAM istifadəsi
ss -tulpn — açıq portlar
lsof -i :<port> — porta baxan proses
curl -X POST -H "Content-Type: application/json" -d '{}' <url> — POST sorğusu
wget <url> — fayl yüklə
tar -czf <name>.tar.gz <dir> — arxiv yarat
tar -xzf <name>.tar.gz — arxivi aç
ssh user@host — uzaq serverə qoş
scp <file> user@host:<path> — uzaq serverə fayl kopyala
export KEY=VALUE — mühit dəyişəni təyin et
echo $KEY — dəyişəni göstər
history — əmr tarixçəsi
which <command> — əmrin yolu
man <command> — sənədləşmə
wc -l <file> — sətir sayı
sort <file> — sırala
awk '{print $1}' <file> — sütun çıxar
sed 's/old/new/g' <file> — mətn əvəz et
xargs — stdin-dən arqument kimi istifadə et
| (pipe) — əmrləri zəncirlə
