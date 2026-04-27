# CLI Task Manager (⭐ Junior)

Terminal vasitəsilə tapşırıqları idarə edən sadə CRUD tətbiqi. Tapşırıqlar `tasks.json` faylında saxlanılır.

## Öyrənilən Konseptlər

- `os.Args` ilə command-line argument oxuma
- JSON serialization / deserialization (`encoding/json`)
- File I/O — `os.ReadFile`, `os.WriteFile`
- Slice əməliyyatları: append, filter, update by index
- `switch` ilə subcommand dispatch

## İşə Salma

```bash
go run main.go                     # help
go run main.go add "Buy groceries"
go run main.go add "Write tests"
go run main.go list
go run main.go done 1
go run main.go delete 2
go run main.go clear               # done olanları sil
```

## Nümunə Output

```
  ✓ [1] Buy groceries
  ○ [2] Write tests
```

## tasks.json Strukturu

```json
[
  {
    "id": 1,
    "title": "Buy groceries",
    "done": true,
    "created_at": "2024-01-15T10:30:00Z"
  }
]
```

## İrəli Getmək Üçün

- `flag` paketi ilə `-file` seçimi
- Due date field əlavə et
- Priority (low / medium / high) dəstəyi
- `cobra` CLI framework-ə keçid
