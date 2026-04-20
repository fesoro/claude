# README Writing — README Yazmaq

## Səviyyə
B1 (GitHub / open source / portfolio)

---

## Niyə Vacibdir?

README = layihənin ön üzü:
- İlk qarşılanan məlumat
- GitHub / portfolio visibility
- Tech interview-da baxılır
- Contributors cəlb edir

**Yaxşı README = professional portfolio**

---

## README Strukturu

```markdown
# Project Name

[Short description]

## Features

## Installation

## Usage

## API / Documentation

## Contributing

## License
```

---

## 1. Title + Tagline

### ✓ Yaxşı

```markdown
# TaskFlow

A minimalist task manager for developers. Built with React + TypeScript.
```

### ✗ Pis

```markdown
# My Project

Some project.
```

### Qaydalar

- Layihə adı (project name)
- 1 cümləlik tagline
- "What it does" + "who it's for"

---

## 2. Badges (Opsional)

Layihə statusu göstərir.

### Common badges

```markdown
![Build Status](https://img.shields.io/...)
![License](https://img.shields.io/...)
![npm version](https://img.shields.io/npm/v/...)
```

### Badge tipləri

- Build status (passing / failing)
- License (MIT, Apache)
- Version (npm, pypi)
- Coverage (%)
- Downloads count
- Contributors

---

## 3. Screenshot / Demo

Vizual göstər.

```markdown
## Demo

![Screenshot](screenshot.png)

Or live demo: [demo-link](https://...)
```

### GIF

Feature demo üçün:
- Animated gif göstər
- Speed: 15-20 fps

---

## 4. Features

Nə edir layihə.

```markdown
## Features

- ✅ Create and manage tasks
- ✅ Dark mode support
- ✅ Real-time sync across devices
- ✅ Offline support
- ✅ Keyboard shortcuts
```

### Bullet points

- Qısa
- Action-oriented
- Concrete (konkret fayda)

---

## 5. Installation

Necə qurmaq lazımdır.

### ✓ Clear instructions

```markdown
## Installation

### Prerequisites
- Node.js 18+
- npm or yarn

### Steps

```bash
# Clone the repository
git clone https://github.com/user/project.git

# Install dependencies
cd project
npm install

# Start development server
npm run dev
```

The app will be available at `http://localhost:3000`.
```

### ✗ Pis

```
Install it.
```

---

## 6. Usage

Necə istifadə etmək.

### Code example

```markdown
## Usage

```python
from myproject import Client

client = Client(api_key="your-key")
result = client.fetch_data("users")
print(result)
```
```

### Interactive examples

Kiçik, fokuslu nümunələr. Mürəkkəb deyil.

---

## 7. Configuration

Environment variables, options.

```markdown
## Configuration

Create a `.env` file:

```env
API_URL=https://api.example.com
API_KEY=your-api-key
DEBUG=false
```

### Options

| Variable | Description | Default |
|----------|-------------|---------|
| `PORT` | Server port | `3000` |
| `LOG_LEVEL` | Logging level | `info` |
```

---

## 8. API / Documentation

API endpoint-ləri / function signatures.

```markdown
## API

### `GET /api/users`

Returns a list of users.

**Query params:**
- `page` (int): page number (default: 1)
- `limit` (int): items per page (default: 20)

**Response:**
```json
{
  "users": [...],
  "total": 100
}
```
```

---

## 9. Contributing

Kömək etmək istəyənlərə.

```markdown
## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md)
for details.

### How to contribute

1. Fork the repo
2. Create a feature branch (`git checkout -b feature/amazing`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing`)
5. Open a Pull Request
```

---

## 10. License

Layihənin lisenziyası.

```markdown
## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
```

### Common licenses

- **MIT** — most permissive
- **Apache 2.0** — similar + patent grant
- **GPL v3** — copyleft (must open-source derivatives)
- **BSD** — permissive
- **Proprietary** — closed source

---

## 11. Credits / Acknowledgments

Kredit ver.

```markdown
## Acknowledgments

- [Library X](https://...) for inspiration
- Thanks to [@username](https://github.com/username) for contributions
- Icon by [Artist Name](https://...)
```

---

## Advanced Sections

### Changelog

```markdown
## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.
```

### Roadmap

```markdown
## Roadmap

- [x] Basic CRUD
- [x] Authentication
- [ ] Mobile app
- [ ] API v2
- [ ] Enterprise features
```

### FAQ

```markdown
## FAQ

**Q: Why does X happen?**
A: Because of Y.

**Q: Can I use this in production?**
A: Yes, it's battle-tested.
```

---

## Portfolio README (Personal Projects)

Portfolio layihələri üçün fokus:

### Elements

- **What it does** (1-2 sentences)
- **Technologies used** (list)
- **Live demo** link
- **Screenshots** (vizual!)
- **Installation** (if contributor-friendly)
- **Future improvements** (shows thinking)

### Example

```markdown
# Weather Dashboard

A real-time weather dashboard showing current conditions
and 7-day forecast for any city.

🌐 [Live Demo](https://myweather.example.com)

## Technologies
- React + TypeScript
- OpenWeather API
- TailwindCSS
- Vercel (deployment)

## Features
- Current weather + forecast
- Location search with autocomplete
- Dark mode
- Responsive design

## Future Improvements
- Weather alerts
- Historical data
- Multi-language support

## Installation
[...]
```

---

## GitHub Features

### Profile README

GitHub `username/username` repo — profile README.

### Pinned repos

Ən yaxşı layihələri pin et.

### Topics

Tag layihəni: `#react`, `#python`, `#machine-learning`.

---

## Writing Tips

### ✓ Do

- **Action verbs**: "build", "create", "run"
- **Present tense**: "This tool **creates**..."
- **Concrete examples**: real code
- **Clear structure**: headings + bullets
- **Keep updated**: eski info çıxar

### ✗ Don't

- Long paragraphs
- Marketing language ("revolutionary", "world-class")
- Unexplained jargon
- Broken links
- Outdated examples

---

## Markdown Tips

### Code blocks

````markdown
```python
def hello():
    print("Hello")
```
````

### Tables

```markdown
| Header 1 | Header 2 |
|----------|----------|
| Cell     | Cell     |
```

### Collapsible sections

```markdown
<details>
<summary>Click to expand</summary>

Hidden content here.

</details>
```

### Emojis

Emoji modern README-də OK:
- ✅ Completed
- 🚧 In progress
- ❌ Not working
- 📚 Documentation

Yalnız user "emoji istəyirəm" deyibsə yaz.

---

## Interview / Portfolio

### Interviewer README-ni oxuyur!

- Professional tone
- Well-structured
- Working demo
- Complete instructions

### Red flags

- "Todo: write README"
- Broken links
- Typos everywhere
- No license
- No description

---

## README Template

Complete template:

```markdown
# Project Name

One-sentence description.

![Screenshot](screenshot.png)

## Features

- Feature 1
- Feature 2

## Demo

[Live Demo](https://...)

## Tech Stack

- Technology 1
- Technology 2

## Installation

```bash
git clone https://github.com/user/project.git
cd project
npm install
npm start
```

## Usage

```code
example
```

## Configuration

| Variable | Description |
|----------|-------------|
| X | Y |

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT License. See [LICENSE](LICENSE).

## Acknowledgments

- Thanks to X
```

---

## Azərbaycanlı Səhvləri

- ✗ Azərbaycanca yazma (international!)
- ✓ **English** README

- ✗ Spelling mistakes (unprofessional)
- ✓ **Spell-check** always

- ✗ No screenshots
- ✓ **Visual** demonstrations

- ✗ "Works on my machine"
- ✓ **Full installation steps**

---

## Xatırlatma

**Yaxşı README:**
1. ✓ Aydın title + tagline
2. ✓ Features list
3. ✓ Installation steps
4. ✓ Usage examples
5. ✓ Configuration
6. ✓ Screenshots
7. ✓ Contributing guide
8. ✓ License

**Qızıl qayda:** İlk dəfə repo-ya daxil olan adamın sualları kimin?
- What is it?
- How to install?
- How to use?

README bu suallara cavab versin.

→ Related: [pr-descriptions.md](pr-descriptions.md), [technical-writing.md](technical-writing.md), [design-doc-writing.md](design-doc-writing.md)
