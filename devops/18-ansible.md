# Ansible (Server Provisioning & Configuration Management)

## Nədir? (What is it?)

Ansible Red Hat tərəfindən dəstəklənən agentless konfiqurasiya idarəetmə və avtomatlaşdırma alətidir. SSH üzərindən işləyir - hədəf serverlərə heç bir agent quraşdırmaq lazım deyil. YAML formatında playbook-lar yazılaraq serverlər konfiqurasiya olunur. Idempotent-dir: eyni playbook-u dəfələrlə çalışdırsanız eyni nəticəni verir.

## Əsas Konseptlər (Key Concepts)

### Quraşdırma

```bash
# Ubuntu/Debian
sudo apt update && sudo apt install ansible

# pip ilə
pip install ansible

# Versiya
ansible --version
```

### Inventory

```ini
# inventory/hosts.ini
[webservers]
web1 ansible_host=10.0.1.10 ansible_user=ubuntu
web2 ansible_host=10.0.1.11 ansible_user=ubuntu

[dbservers]
db1 ansible_host=10.0.2.10 ansible_user=ubuntu

[redis]
cache1 ansible_host=10.0.3.10

[production:children]
webservers
dbservers
redis

[production:vars]
ansible_ssh_private_key_file=~/.ssh/production.pem
app_env=production
```

```yaml
# inventory/hosts.yml (YAML format)
all:
  children:
    production:
      children:
        webservers:
          hosts:
            web1:
              ansible_host: 10.0.1.10
            web2:
              ansible_host: 10.0.1.11
        dbservers:
          hosts:
            db1:
              ansible_host: 10.0.2.10
      vars:
        ansible_user: ubuntu
        ansible_ssh_private_key_file: ~/.ssh/production.pem
        app_env: production
```

### Ad-hoc Əmrlər

```bash
# Bütün serverlərə ping
ansible all -m ping -i inventory/hosts.ini

# Webserverlərə əmr göndər
ansible webservers -m shell -a "uptime" -i inventory/hosts.ini

# Fayl kopyala
ansible webservers -m copy -a "src=./app.conf dest=/etc/app.conf" -i inventory/hosts.ini

# Paket quraşdır
ansible webservers -m apt -a "name=nginx state=present" -i inventory/hosts.ini --become

# Service restart
ansible webservers -m service -a "name=nginx state=restarted" -i inventory/hosts.ini --become

# Disk istifadəsini yoxla
ansible all -m shell -a "df -h /" -i inventory/hosts.ini
```

### Playbook Əsasları

```yaml
# playbooks/setup-webserver.yml
---
- name: Setup Laravel web server
  hosts: webservers
  become: yes
  vars:
    php_version: "8.3"
    app_dir: /var/www/laravel
    app_user: www-data

  tasks:
    - name: Update apt cache
      apt:
        update_cache: yes
        cache_valid_time: 3600

    - name: Install required packages
      apt:
        name:
          - nginx
          - "php{{ php_version }}-fpm"
          - "php{{ php_version }}-mysql"
          - "php{{ php_version }}-redis"
          - "php{{ php_version }}-xml"
          - "php{{ php_version }}-curl"
          - "php{{ php_version }}-mbstring"
          - "php{{ php_version }}-zip"
          - "php{{ php_version }}-gd"
          - composer
          - git
          - supervisor
        state: present

    - name: Create application directory
      file:
        path: "{{ app_dir }}"
        state: directory
        owner: "{{ app_user }}"
        group: "{{ app_user }}"
        mode: '0755'

    - name: Deploy Nginx config
      template:
        src: templates/nginx.conf.j2
        dest: /etc/nginx/sites-available/laravel.conf
        owner: root
        group: root
        mode: '0644'
      notify: Reload Nginx

    - name: Enable site
      file:
        src: /etc/nginx/sites-available/laravel.conf
        dest: /etc/nginx/sites-enabled/laravel.conf
        state: link
      notify: Reload Nginx

    - name: Remove default site
      file:
        path: /etc/nginx/sites-enabled/default
        state: absent
      notify: Reload Nginx

    - name: Deploy PHP-FPM pool config
      template:
        src: templates/php-fpm-pool.conf.j2
        dest: "/etc/php/{{ php_version }}/fpm/pool.d/laravel.conf"
      notify: Restart PHP-FPM

    - name: Deploy .env file
      template:
        src: templates/env.j2
        dest: "{{ app_dir }}/.env"
        owner: "{{ app_user }}"
        group: "{{ app_user }}"
        mode: '0600'

    - name: Set storage permissions
      file:
        path: "{{ app_dir }}/storage"
        state: directory
        owner: "{{ app_user }}"
        group: "{{ app_user }}"
        mode: '0775'
        recurse: yes

    - name: Ensure services are running
      service:
        name: "{{ item }}"
        state: started
        enabled: yes
      loop:
        - nginx
        - "php{{ php_version }}-fpm"

  handlers:
    - name: Reload Nginx
      service:
        name: nginx
        state: reloaded

    - name: Restart PHP-FPM
      service:
        name: "php{{ php_version }}-fpm"
        state: restarted
```

### Templates (Jinja2)

```nginx
# templates/nginx.conf.j2
server {
    listen 80;
    server_name {{ domain }};
    root {{ app_dir }}/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php{{ php_version }}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }

{% if ssl_enabled | default(false) %}
    listen 443 ssl http2;
    ssl_certificate /etc/letsencrypt/live/{{ domain }}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{{ domain }}/privkey.pem;
{% endif %}
}
```

```ini
# templates/env.j2
APP_NAME={{ app_name | default('Laravel') }}
APP_ENV={{ app_env }}
APP_KEY={{ app_key }}
APP_DEBUG={{ 'true' if app_env == 'development' else 'false' }}
APP_URL=https://{{ domain }}

DB_CONNECTION=mysql
DB_HOST={{ db_host }}
DB_PORT=3306
DB_DATABASE={{ db_name }}
DB_USERNAME={{ db_username }}
DB_PASSWORD={{ db_password }}

REDIS_HOST={{ redis_host | default('127.0.0.1') }}
REDIS_PORT=6379

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Roles

```bash
# Role strukturu
roles/
├── laravel/
│   ├── tasks/
│   │   ├── main.yml        # Əsas task-lar
│   │   ├── install.yml      # Quraşdırma
│   │   ├── configure.yml    # Konfiqurasiya
│   │   └── deploy.yml       # Deploy
│   ├── handlers/
│   │   └── main.yml
│   ├── templates/
│   │   ├── nginx.conf.j2
│   │   ├── env.j2
│   │   └── supervisor.conf.j2
│   ├── files/
│   │   └── logrotate.conf
│   ├── vars/
│   │   └── main.yml
│   ├── defaults/
│   │   └── main.yml         # Default dəyişənlər
│   └── meta/
│       └── main.yml         # Dependencies
```

```yaml
# roles/laravel/defaults/main.yml
php_version: "8.3"
app_dir: /var/www/laravel
app_user: www-data
domain: example.com
app_env: production

# roles/laravel/tasks/main.yml
---
- name: Install dependencies
  import_tasks: install.yml

- name: Configure application
  import_tasks: configure.yml

- name: Deploy application
  import_tasks: deploy.yml
  tags: deploy

# roles/laravel/tasks/deploy.yml
---
- name: Pull latest code
  git:
    repo: "{{ git_repo }}"
    dest: "{{ app_dir }}"
    version: "{{ git_branch | default('main') }}"
    force: yes
  become_user: "{{ app_user }}"

- name: Install Composer dependencies
  composer:
    command: install
    working_dir: "{{ app_dir }}"
    no_dev: "{{ app_env == 'production' }}"
    optimize_autoloader: yes
  become_user: "{{ app_user }}"

- name: Run migrations
  command: php artisan migrate --force
  args:
    chdir: "{{ app_dir }}"
  become_user: "{{ app_user }}"
  when: run_migrations | default(true)

- name: Cache config
  command: "php artisan {{ item }}"
  args:
    chdir: "{{ app_dir }}"
  become_user: "{{ app_user }}"
  loop:
    - config:cache
    - route:cache
    - view:cache
    - event:cache

- name: Restart queue workers
  command: php artisan queue:restart
  args:
    chdir: "{{ app_dir }}"
  become_user: "{{ app_user }}"

# Role istifadə etmək
# playbooks/site.yml
---
- name: Deploy Laravel application
  hosts: webservers
  become: yes
  roles:
    - role: laravel
      vars:
        domain: example.com
        git_repo: git@github.com:company/laravel-app.git
        git_branch: main
```

### Ansible Vault

```bash
# Vault - sensitive data-nı şifrələmək

# Şifrəli fayl yaratmaq
ansible-vault create group_vars/production/vault.yml

# Fayl şifrələmək
ansible-vault encrypt group_vars/production/secrets.yml

# Faylı deşifrələmək
ansible-vault decrypt group_vars/production/secrets.yml

# Faylı redaktə etmək
ansible-vault edit group_vars/production/vault.yml

# String şifrələmək
ansible-vault encrypt_string 'my_secret_password' --name 'db_password'

# Playbook-u vault ilə çalışdırmaq
ansible-playbook site.yml --ask-vault-pass
ansible-playbook site.yml --vault-password-file ~/.vault_pass
```

```yaml
# group_vars/production/vault.yml (şifrələnmiş)
vault_db_password: SuperSecret123
vault_app_key: base64:xxxxxxxxxxxxx
vault_redis_password: RedisPass456

# group_vars/production/vars.yml (açıq, vault dəyişənlərə istinad)
db_password: "{{ vault_db_password }}"
app_key: "{{ vault_app_key }}"
redis_password: "{{ vault_redis_password }}"
```

### Handlers

```yaml
# handlers/main.yml
---
- name: Reload Nginx
  service:
    name: nginx
    state: reloaded

- name: Restart PHP-FPM
  service:
    name: "php{{ php_version }}-fpm"
    state: restarted

- name: Restart Supervisor
  service:
    name: supervisor
    state: restarted

- name: Restart Redis
  service:
    name: redis-server
    state: restarted

# Handlers yalnız notify olunduqda, playbook sonunda işləyir
# Eyni handler bir neçə dəfə notify olunsa, yalnız bir dəfə işləyir
```

## Praktiki Nümunələr (Practical Examples)

### Laravel Server Provisioning Playbook

```yaml
# playbooks/provision.yml
---
- name: Provision Laravel server from scratch
  hosts: webservers
  become: yes
  vars_files:
    - vars/common.yml
    - vars/{{ app_env }}.yml

  pre_tasks:
    - name: Update system
      apt:
        upgrade: dist
        update_cache: yes

    - name: Set timezone
      timezone:
        name: "{{ timezone | default('UTC') }}"

    - name: Configure swap
      include_tasks: tasks/swap.yml
      when: ansible_memtotal_mb < 2048

  roles:
    - role: geerlingguy.security      # SSH hardening
    - role: geerlingguy.firewall       # UFW
    - role: geerlingguy.php            # PHP
    - role: geerlingguy.nginx          # Nginx
    - role: geerlingguy.mysql          # MySQL
    - role: geerlingguy.redis          # Redis
    - role: geerlingguy.composer       # Composer
    - role: laravel                    # Custom Laravel role

  post_tasks:
    - name: Setup cron for Laravel scheduler
      cron:
        name: "Laravel Scheduler"
        minute: "*"
        job: "cd {{ app_dir }} && php artisan schedule:run >> /dev/null 2>&1"
        user: "{{ app_user }}"

    - name: Setup Supervisor for queue workers
      template:
        src: templates/supervisor-worker.conf.j2
        dest: /etc/supervisor/conf.d/laravel-worker.conf
      notify: Restart Supervisor
```

### Rolling Deploy Playbook

```yaml
# playbooks/deploy.yml
---
- name: Rolling deploy Laravel
  hosts: webservers
  become: yes
  serial: 1                    # Bir-bir deploy et (rolling)
  max_fail_percentage: 0       # Heç bir server uğursuz olmamalı

  pre_tasks:
    - name: Disable server in load balancer
      uri:
        url: "http://{{ lb_host }}/api/servers/{{ inventory_hostname }}/disable"
        method: POST
      delegate_to: localhost

    - name: Wait for connections to drain
      pause:
        seconds: 30

  tasks:
    - name: Enable maintenance mode
      command: php artisan down --retry=60
      args:
        chdir: "{{ app_dir }}"

    - name: Pull latest code
      git:
        repo: "{{ git_repo }}"
        dest: "{{ app_dir }}"
        version: "{{ deploy_version | default('main') }}"

    - name: Install dependencies
      composer:
        command: install
        working_dir: "{{ app_dir }}"
        no_dev: yes

    - name: Run migrations
      command: php artisan migrate --force
      args:
        chdir: "{{ app_dir }}"
      run_once: true           # Yalnız bir serverdə migration çalışdır

    - name: Cache configuration
      command: "php artisan {{ item }}"
      args:
        chdir: "{{ app_dir }}"
      loop: [config:cache, route:cache, view:cache]

    - name: Disable maintenance mode
      command: php artisan up
      args:
        chdir: "{{ app_dir }}"

  post_tasks:
    - name: Health check
      uri:
        url: "http://localhost/health"
        status_code: 200
      retries: 5
      delay: 5

    - name: Enable server in load balancer
      uri:
        url: "http://{{ lb_host }}/api/servers/{{ inventory_hostname }}/enable"
        method: POST
      delegate_to: localhost
```

## PHP/Laravel ilə İstifadə

### Supervisor Template for Queue Workers

```ini
# templates/supervisor-worker.conf.j2
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php {{ app_dir }}/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user={{ app_user }}
numprocs={{ queue_workers | default(3) }}
redirect_stderr=true
stdout_logfile={{ app_dir }}/storage/logs/worker.log
stopwaitsecs=3600
```

## Interview Sualları

### S1: Ansible niyə agentless-dir və bunun üstünlüyü nədir?
**C:** Ansible hədəf serverlərə SSH ilə qoşulur, əlavə agent/daemon quraşdırmaq lazım deyil. Yalnız SSH və Python olmalıdır. Üstünlükləri: 1) Quraşdırma sadədir, 2) Təhlükəsizlik - əlavə port/servis açmaq lazım deyil, 3) Resursa qənaət - daemon işləmir, 4) Yeni serverlər dərhal idarə oluna bilər. Chef və Puppet agent tələb edir.

### S2: Playbook, Role, Task arasında fərq nədir?
**C:** **Task**: tək bir əməliyyat (paket quraşdır, fayl kopyala). **Playbook**: task-ların toplusu, hansı hostlarda nə edəcəyini bildirir. **Role**: təkrar istifadə olunan, strukturlaşdırılmış playbook (tasks/, handlers/, templates/, vars/). Playbook bir neçə role çağıra bilər. Role-lar Galaxy-dən paylaşıla bilər.

### S3: Ansible idempotent nə deməkdir?
**C:** Eyni playbook-u dəfələrlə çalışdırsanız nəticə eyni olur. Məsələn "nginx quraşdır" task-ı - əgər nginx artıq quraşdırılıbsa, heç nə etmir (changed=0). Bu shell scriptlərdən fərqdir. Ansible modulları (apt, service, file) idempotent-dir. shell/command modulları default olaraq idempotent deyil, `creates`/`removes` parametrləri ilə idempotent edilə bilər.

### S4: Ansible Vault nə üçün istifadə olunur?
**C:** Sensitive data-nı (password, API key, sertifikat) şifrələmək üçün. AES-256 ilə şifrələyir. Fayl və ya string səviyyəsində şifrələmə mümkündür. `ansible-vault encrypt/decrypt/edit` əmrləri ilə idarə olunur. Playbook-u `--ask-vault-pass` və ya `--vault-password-file` ilə çalışdırırsan. Git-də saxlamaq təhlükəsizdir (şifrəli olduğu üçün).

### S5: Ansible vs Terraform fərqi nədir?
**C:** **Terraform**: deklarativ IaC, infrastruktur yaratmaq üçün (EC2, VPC, RDS), state-based, plan/apply döngüsü. **Ansible**: prosedural/deklarativ, server konfiqurasiyası üçün (PHP, Nginx quraşdırma, deploy), agentless, SSH ilə. Birlikdə istifadə: Terraform serveri yaradır, Ansible konfiqurasiya edir. Terraform EC2 yaradır -> Ansible PHP/Nginx quraşdırır -> Laravel deploy edir.

### S6: serial direktivi nə edir?
**C:** `serial: 1` playbook-u hostlara ardıcıl tətbiq edir - bir-bir. Default olaraq Ansible bütün hostlara paralel işləyir. Rolling deploy üçün istifadə olunur: 1 server deploy olunur, health check keçir, sonra növbəti. `serial: "25%"` kimi faiz də vermək olur. `max_fail_percentage: 0` - heç bir server uğursuz olmamalıdır.

## Best Practices

1. **Role istifadə edin** - Təkrar istifadə olunan, test edilə bilən kod
2. **Vault** ilə sensitive data şifrələyin
3. **İdempotent** task-lar yazın - shell/command-dan qaçın, modullara üstünlük verin
4. **Tags** istifadə edin - `ansible-playbook site.yml --tags deploy`
5. **group_vars/host_vars** ilə dəyişənləri təşkil edin
6. **ansible-lint** istifadə edin - Kod keyfiyyəti
7. **Molecule** ilə role-ları test edin
8. **Check mode** istifadə edin - `--check --diff` (dry-run)
9. **Handler** istifadə edin - Service restart-ı optimallaşdırın
10. **Logging** aktiv edin - `ANSIBLE_LOG_PATH` environment variable
