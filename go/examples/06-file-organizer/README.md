# File Organizer (⭐ Junior)

Faylları extension-a görə avtomatik qovluqlara ayıran CLI aləti. Dry-run modu ilə əvvəlcə preview et.

## Öyrənilən Konseptlər

- `flag` paketi ilə flags parsing
- `os.ReadDir` ilə directory listing
- `filepath.Ext` ilə extension çıxarma
- `os.Rename` ilə fayl köçürmə
- `os.MkdirAll` ilə nested directory yaratma
- Map-lə O(1) kategori axtarışı

## Kateqoriyalar

| Qovluq | Extension-lar |
|--------|--------------|
| `images/` | .jpg .png .gif .webp .svg .ico |
| `videos/` | .mp4 .mkv .avi .mov .flv |
| `audio/` | .mp3 .wav .flac .aac .ogg |
| `documents/` | .pdf .doc .docx .xls .pptx |
| `text/` | .txt .md .csv .json .yaml .toml |
| `code/` | .go .php .js .py .java .sh .sql |
| `archives/` | .zip .tar .gz .rar .7z |
| `others/` | tanınmayan hər şey |

## İşə Salma

```bash
# Dry-run: yalnız preview, heç nə köçürülmür
go run main.go -dir ~/Downloads -dry

# Həqiqi icra
go run main.go -dir ~/Downloads

# Cari qovluğu təşkil et
go run main.go
```

## Nümunə Output

```
Organizing: /home/user/Downloads
(dry-run — no files will be moved)

  [dry] photo.jpg                          →  images/
  [dry] resume.pdf                         →  documents/
  [dry] backup.zip                         →  archives/
  [dry] main.go                            →  code/
```

## İrəli Getmək Üçün

- Tarixə görə alt-qovluqlar: `images/2024/01/`
- Duplicate aşkarı (MD5 hash müqayisəsi)
- Undo log (`move.log` faylı saxla)
- Recursive subdirectory dəstəyi
