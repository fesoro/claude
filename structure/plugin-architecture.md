# Plugin (Microkernel) Architecture

The core system provides minimal functionality, with features added through plugins.
The kernel handles core operations; plugins extend behavior without modifying the core.

**Key concepts:**
- **Core/Kernel** — Minimal system with plugin management and core services
- **Plugin** — Self-contained extension that adds functionality
- **Plugin Interface** — Contract that all plugins must implement
- **Plugin Registry** — Discovers, loads, and manages plugins
- **Extension Point** — Where plugins can hook into the core
- **Plugin Lifecycle** — Install, activate, deactivate, uninstall

---

## Laravel

```
app/
├── Core/                                   # Kernel (minimal core)
│   ├── Kernel.php
│   ├── Plugin/
│   │   ├── PluginInterface.php            # Contract all plugins implement
│   │   ├── PluginRegistry.php             # Discovers and manages plugins
│   │   ├── PluginLoader.php               # Loads plugin code
│   │   ├── PluginManager.php              # Lifecycle management
│   │   ├── PluginManifest.php             # Plugin metadata
│   │   └── ExtensionPoint/
│   │       ├── ExtensionPointInterface.php
│   │       ├── HookManager.php            # Action/filter hooks
│   │       ├── MenuExtension.php
│   │       ├── RouteExtension.php
│   │       └── WidgetExtension.php
│   │
│   ├── Services/                           # Core services (minimal)
│   │   ├── AuthService.php
│   │   ├── SettingsService.php
│   │   └── PermissionService.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Setting.php
│   │   └── Plugin.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DashboardController.php
│   │   │   ├── SettingsController.php
│   │   │   └── PluginManagerController.php
│   │   └── Middleware/
│   │       └── PluginMiddleware.php
│   │
│   └── Providers/
│       └── CoreServiceProvider.php
│
├── Plugins/                                # Plugin directory
│   ├── BlogPlugin/
│   │   ├── BlogPlugin.php                 # Implements PluginInterface
│   │   ├── plugin.json                    # Manifest
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── PostController.php
│   │   │   └── Resources/
│   │   ├── Models/
│   │   │   ├── Post.php
│   │   │   └── Comment.php
│   │   ├── Services/
│   │   │   └── PostService.php
│   │   ├── Routes/
│   │   │   └── routes.php
│   │   ├── Views/
│   │   ├── Migrations/
│   │   ├── Config/
│   │   │   └── blog.php
│   │   └── Tests/
│   │
│   ├── EcommercePlugin/
│   │   ├── EcommercePlugin.php
│   │   ├── plugin.json
│   │   ├── Http/
│   │   │   └── Controllers/
│   │   │       ├── ProductController.php
│   │   │       ├── CartController.php
│   │   │       └── CheckoutController.php
│   │   ├── Models/
│   │   │   ├── Product.php
│   │   │   ├── Cart.php
│   │   │   └── Order.php
│   │   ├── Services/
│   │   ├── Routes/
│   │   ├── Views/
│   │   ├── Migrations/
│   │   └── Tests/
│   │
│   └── AnalyticsPlugin/
│       ├── AnalyticsPlugin.php
│       ├── plugin.json
│       ├── Services/
│       │   └── AnalyticsService.php
│       ├── Widgets/
│       │   ├── TrafficWidget.php
│       │   └── RevenueWidget.php
│       └── Tests/

routes/
config/
database/
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── core/                                   # Kernel
│   ├── Application.java
│   ├── plugin/
│   │   ├── Plugin.java                    # Interface
│   │   ├── PluginRegistry.java
│   │   ├── PluginLoader.java
│   │   ├── PluginManager.java
│   │   ├── PluginManifest.java
│   │   ├── PluginLifecycle.java
│   │   └── extension/
│   │       ├── ExtensionPoint.java
│   │       ├── HookManager.java
│   │       ├── MenuExtension.java
│   │       └── RouteExtension.java
│   │
│   ├── service/
│   │   ├── AuthService.java
│   │   └── SettingsService.java
│   │
│   ├── controller/
│   │   ├── DashboardController.java
│   │   └── PluginManagerController.java
│   │
│   ├── entity/
│   │   ├── User.java
│   │   └── Setting.java
│   │
│   └── config/
│       └── CoreConfig.java
│
├── plugin/                                 # Plugins
│   ├── blog/
│   │   ├── BlogPlugin.java
│   │   ├── manifest.json
│   │   ├── controller/
│   │   │   └── PostController.java
│   │   ├── service/
│   │   │   └── PostService.java
│   │   ├── entity/
│   │   │   ├── Post.java
│   │   │   └── Comment.java
│   │   ├── repository/
│   │   │   └── PostRepository.java
│   │   └── config/
│   │       └── BlogPluginConfig.java
│   │
│   ├── ecommerce/
│   │   ├── EcommercePlugin.java
│   │   ├── manifest.json
│   │   ├── controller/
│   │   │   ├── ProductController.java
│   │   │   └── CartController.java
│   │   ├── service/
│   │   ├── entity/
│   │   └── config/
│   │
│   └── analytics/
│       ├── AnalyticsPlugin.java
│       ├── manifest.json
│       ├── service/
│       └── widget/

src/main/resources/
├── application.yml
├── plugins/                                # External plugin JARs
└── db/migration/
```

---

## Golang

```
project/
├── cmd/
│   └── api/
│       └── main.go
│
├── internal/
│   ├── core/                               # Kernel
│   │   ├── app.go
│   │   ├── plugin/
│   │   │   ├── plugin.go                  # Plugin interface
│   │   │   ├── registry.go
│   │   │   ├── loader.go
│   │   │   ├── manager.go
│   │   │   ├── manifest.go
│   │   │   └── extension/
│   │   │       ├── extension_point.go
│   │   │       ├── hook_manager.go
│   │   │       └── route_extension.go
│   │   │
│   │   ├── service/
│   │   │   ├── auth_service.go
│   │   │   └── settings_service.go
│   │   │
│   │   ├── handler/
│   │   │   ├── dashboard_handler.go
│   │   │   └── plugin_manager_handler.go
│   │   │
│   │   ├── model/
│   │   │   ├── user.go
│   │   │   └── setting.go
│   │   │
│   │   └── config/
│   │       └── config.go
│   │
│   └── plugin/                             # Plugins
│       ├── blog/
│       │   ├── blog_plugin.go             # Implements Plugin interface
│       │   ├── manifest.json
│       │   ├── handler/
│       │   │   └── post_handler.go
│       │   ├── service/
│       │   │   └── post_service.go
│       │   ├── model/
│       │   │   ├── post.go
│       │   │   └── comment.go
│       │   ├── repository/
│       │   │   └── post_repo.go
│       │   └── migration/
│       │
│       ├── ecommerce/
│       │   ├── ecommerce_plugin.go
│       │   ├── manifest.json
│       │   ├── handler/
│       │   ├── service/
│       │   ├── model/
│       │   └── repository/
│       │
│       └── analytics/
│           ├── analytics_plugin.go
│           ├── manifest.json
│           ├── service/
│           └── widget/
│
├── plugins/                                # External plugin binaries (.so)
│   └── README.md
├── pkg/
├── go.mod
└── Makefile
```
