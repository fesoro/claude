# Jenkins (Middle)

## Nədir? (What is it?)

Jenkins açıq mənbəli (open-source) avtomatlaşdırma serveridir. CI/CD pipeline-ları qurmaq, build, test və deploy proseslərini avtomatlaşdırmaq üçün istifadə olunur. Java ilə yazılıb, 1800+ plugin dəstəkləyir. On-premise quraşdırılır və tam kontrol verir.

Jenkins 2005-ci ildə Hudson adı ilə yaranıb, sonra fork olaraq Jenkins olub. Hələ də ən geniş yayılmış CI/CD tool-lardan biridir, xüsusilə enterprise mühitlərdə.

## Əsas Konseptlər (Key Concepts)

### Jenkins Arxitekturası

```
┌─────────────────────────────────────┐
│           Jenkins Master            │
│  ┌──────────┐  ┌────────────────┐   │
│  │ Web UI   │  │ Job Scheduler  │   │
│  └──────────┘  └────────────────┘   │
│  ┌──────────┐  ┌────────────────┐   │
│  │ REST API │  │ Plugin Manager │   │
│  └──────────┘  └────────────────┘   │
└───────────┬─────────────────────────┘
            │ (distributes jobs)
    ┌───────┼───────┐
    ▼       ▼       ▼
┌───────┐┌───────┐┌───────┐
│Agent 1││Agent 2││Agent 3│
│(Linux)││(macOS)││(Docker│
└───────┘└───────┘└───────┘
```

**Master (Controller):** Job-ları schedule edir, agent-ları idarə edir, UI təmin edir, konfiqurasiyanı saxlayır.

**Agent (Node):** Build-ləri icra edir. Master-ə SSH və ya JNLP ilə qoşulur. Label-lər ilə qruplaşdırılır.

### Jenkinsfile

Jenkinsfile - pipeline-ı kod kimi təyin edən fayldır. Repository-nin root-unda saxlanılır.

**Declarative Pipeline (tövsiyə olunan):**

```groovy
// Jenkinsfile (Declarative)
pipeline {
    agent any

    environment {
        APP_ENV = 'testing'
        DB_CONNECTION = 'sqlite'
    }

    options {
        timeout(time: 30, unit: 'MINUTES')
        timestamps()
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }

    triggers {
        pollSCM('H/5 * * * *')  // Hər 5 dəqiqə SCM yoxla
    }

    stages {
        stage('Install') {
            steps {
                sh 'composer install --no-interaction'
                sh 'npm ci'
            }
        }

        stage('Code Quality') {
            parallel {
                stage('Lint') {
                    steps {
                        sh './vendor/bin/pint --test'
                    }
                }
                stage('PHPStan') {
                    steps {
                        sh './vendor/bin/phpstan analyse'
                    }
                }
            }
        }

        stage('Test') {
            steps {
                sh 'cp .env.testing .env'
                sh 'php artisan key:generate'
                sh 'php artisan test --log-junit results.xml'
            }
            post {
                always {
                    junit 'results.xml'
                }
            }
        }

        stage('Build') {
            steps {
                sh 'npm run build'
            }
        }

        stage('Deploy Staging') {
            when {
                branch 'develop'
            }
            steps {
                sh './deploy.sh staging'
            }
        }

        stage('Deploy Production') {
            when {
                branch 'main'
            }
            input {
                message "Deploy to production?"
                ok "Yes, deploy!"
                submitter "admin,devops-team"
            }
            steps {
                sh './deploy.sh production'
            }
        }
    }

    post {
        success {
            slackSend(color: 'good', message: "Build SUCCESS: ${env.JOB_NAME} #${env.BUILD_NUMBER}")
        }
        failure {
            slackSend(color: 'danger', message: "Build FAILED: ${env.JOB_NAME} #${env.BUILD_NUMBER}")
        }
        always {
            cleanWs()  // Workspace təmizlə
        }
    }
}
```

**Scripted Pipeline (köhnə üsul, daha çevik):**

```groovy
// Jenkinsfile (Scripted)
node('linux') {
    try {
        stage('Checkout') {
            checkout scm
        }

        stage('Install') {
            sh 'composer install --no-interaction'
        }

        stage('Test') {
            sh 'php artisan test'
        }

        if (env.BRANCH_NAME == 'main') {
            stage('Deploy') {
                input message: 'Deploy to production?'
                sh './deploy.sh'
            }
        }

        currentBuild.result = 'SUCCESS'
    } catch (e) {
        currentBuild.result = 'FAILURE'
        throw e
    } finally {
        // Notification
        emailext(
            subject: "${currentBuild.result}: ${env.JOB_NAME}",
            body: "Build #${env.BUILD_NUMBER} ${currentBuild.result}",
            to: 'team@example.com'
        )
    }
}
```

### Declarative vs Scripted Pipeline

| Xüsusiyyət | Declarative | Scripted |
|-------------|-------------|----------|
| Syntax | Strukturlaşdırılmış | Groovy scripting |
| Öyrənmə | Asan | Çətin |
| Çeviklik | Məhdud | Tam Groovy gücü |
| Error handling | post {} bloku | try/catch/finally |
| when conditions | Built-in | if/else |
| Parallel | parallel {} bloku | parallel {} map |
| Tövsiyə | Yeni layihələr üçün | Kompleks logic lazım olanda |

### Agents

```groovy
pipeline {
    // Hər hansı agent
    agent any

    // Konkret label
    agent { label 'linux && docker' }

    // Docker container
    agent {
        docker {
            image 'php:8.3-cli'
            args '-v /tmp:/tmp'
        }
    }

    // Dockerfile-dan
    agent {
        dockerfile {
            filename 'Dockerfile.ci'
            dir 'docker'
            args '-v /tmp:/tmp'
        }
    }

    // Stage-specific agent
    stages {
        stage('Test') {
            agent { docker { image 'php:8.3-cli' } }
            steps { sh 'php artisan test' }
        }
        stage('Build JS') {
            agent { docker { image 'node:20' } }
            steps { sh 'npm run build' }
        }
    }
}
```

### Plugins

Ən vacib Jenkins pluginləri:

```
Pipeline              - Pipeline as code support
Git                   - Git SCM integration
Docker Pipeline       - Docker agent support
Blue Ocean            - Modern UI
Credentials           - Credentials management
Slack Notification    - Slack integration
JUnit                 - Test results
HTML Publisher        - HTML reports
SSH Agent             - SSH key management
Role-based Access     - RBAC
Multibranch Pipeline  - Branch-based pipelines
Shared Libraries      - Reusable pipeline code
```

### Shared Libraries

```groovy
// vars/laravelPipeline.groovy (Shared Library)
def call(Map config = [:]) {
    pipeline {
        agent any

        environment {
            PHP_VERSION = config.phpVersion ?: '8.3'
        }

        stages {
            stage('Install') {
                steps {
                    sh "composer install --no-interaction"
                }
            }

            stage('Test') {
                steps {
                    sh "php artisan test"
                }
            }

            stage('Deploy') {
                when { branch 'main' }
                steps {
                    sh "./deploy.sh ${config.deployTarget ?: 'production'}"
                }
            }
        }
    }
}

// Jenkinsfile (istifadə)
@Library('my-shared-lib') _

laravelPipeline(
    phpVersion: '8.3',
    deployTarget: 'production'
)
```

```groovy
// vars/deployLaravel.groovy
def call(String server, String path) {
    withCredentials([sshUserPrivateKey(credentialsId: 'deploy-key', keyFileVariable: 'SSH_KEY')]) {
        sh """
            ssh -i \$SSH_KEY deploy@${server} '
                cd ${path}
                php artisan down --secret=bypass
                git pull origin main
                composer install --no-dev --optimize-autoloader
                php artisan migrate --force
                php artisan config:cache
                php artisan route:cache
                php artisan view:cache
                php artisan queue:restart
                php artisan up
            '
        """
    }
}

// Jenkinsfile-da istifadə
stage('Deploy') {
    steps {
        deployLaravel('prod-server.example.com', '/var/www/laravel')
    }
}
```

### Credentials Management

```groovy
pipeline {
    stages {
        stage('Deploy') {
            steps {
                // SSH Key
                withCredentials([sshUserPrivateKey(
                    credentialsId: 'deploy-ssh-key',
                    keyFileVariable: 'SSH_KEY',
                    usernameVariable: 'SSH_USER'
                )]) {
                    sh 'ssh -i $SSH_KEY $SSH_USER@server "deploy.sh"'
                }

                // Username/Password
                withCredentials([usernamePassword(
                    credentialsId: 'db-credentials',
                    usernameVariable: 'DB_USER',
                    passwordVariable: 'DB_PASS'
                )]) {
                    sh 'mysql -u $DB_USER -p$DB_PASS < migration.sql'
                }

                // Secret Text
                withCredentials([string(
                    credentialsId: 'slack-webhook',
                    variable: 'WEBHOOK_URL'
                )]) {
                    sh 'curl -X POST $WEBHOOK_URL -d "Deploy done"'
                }
            }
        }
    }
}
```

## Praktiki Nümunələr (Practical Examples)

### Complete Laravel Jenkins Pipeline

```groovy
// Jenkinsfile
pipeline {
    agent { label 'linux' }

    environment {
        APP_ENV = 'testing'
        COMPOSER_HOME = "${WORKSPACE}/.composer"
    }

    options {
        timeout(time: 30, unit: 'MINUTES')
        timestamps()
        ansiColor('xterm')
        disableConcurrentBuilds(abortPrevious: true)
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
                stash name: 'source', includes: '**'
            }
        }

        stage('Install Dependencies') {
            parallel {
                stage('Composer') {
                    steps {
                        sh '''
                            composer install \
                                --no-interaction \
                                --prefer-dist \
                                --optimize-autoloader
                        '''
                    }
                }
                stage('npm') {
                    agent { docker { image 'node:20-alpine' } }
                    steps {
                        unstash 'source'
                        sh 'npm ci'
                        sh 'npm run build'
                        stash name: 'frontend', includes: 'public/build/**'
                    }
                }
            }
        }

        stage('Quality Checks') {
            parallel {
                stage('Pint') {
                    steps {
                        sh './vendor/bin/pint --test'
                    }
                }
                stage('PHPStan') {
                    steps {
                        sh './vendor/bin/phpstan analyse --error-format=github'
                    }
                }
                stage('Security') {
                    steps {
                        sh 'composer audit --format=json > security-report.json'
                    }
                }
            }
        }

        stage('Test') {
            steps {
                sh '''
                    cp .env.testing .env
                    php artisan key:generate
                    touch database/testing.sqlite
                    php artisan test \
                        --log-junit test-results.xml \
                        --coverage-clover coverage.xml
                '''
            }
            post {
                always {
                    junit testResults: 'test-results.xml', allowEmptyResults: true
                    publishHTML([
                        reportDir: 'storage/logs',
                        reportFiles: 'laravel.log',
                        reportName: 'Laravel Logs'
                    ])
                }
            }
        }

        stage('Build Artifact') {
            when { anyOf { branch 'main'; branch 'develop' } }
            steps {
                unstash 'frontend'
                sh '''
                    tar -czf release-${BUILD_NUMBER}.tar.gz \
                        --exclude='.git' \
                        --exclude='node_modules' \
                        --exclude='tests' \
                        --exclude='.env*' \
                        .
                '''
                archiveArtifacts artifacts: "release-${BUILD_NUMBER}.tar.gz"
            }
        }

        stage('Deploy Staging') {
            when { branch 'develop' }
            steps {
                deployLaravel('staging.example.com', '/var/www/staging')
            }
        }

        stage('Deploy Production') {
            when { branch 'main' }
            input {
                message "Deploy build #${BUILD_NUMBER} to production?"
                ok "Deploy"
                submitter "admin,lead-dev"
                parameters {
                    booleanParam(name: 'RUN_MIGRATIONS', defaultValue: true)
                }
            }
            steps {
                script {
                    if (params.RUN_MIGRATIONS) {
                        deployLaravel('prod.example.com', '/var/www/production')
                    }
                }
            }
        }
    }

    post {
        success {
            slackSend(
                color: 'good',
                message: ":white_check_mark: *${env.JOB_NAME}* #${env.BUILD_NUMBER} SUCCESS\nBranch: ${env.BRANCH_NAME}\n${env.BUILD_URL}"
            )
        }
        failure {
            slackSend(
                color: 'danger',
                message: ":x: *${env.JOB_NAME}* #${env.BUILD_NUMBER} FAILED\nBranch: ${env.BRANCH_NAME}\n${env.BUILD_URL}"
            )
        }
        always {
            cleanWs()
        }
    }
}
```

### Multibranch Pipeline Setup

```groovy
// Jenkins multibranch pipeline avtomatik olaraq
// repository-dəki hər branch üçün ayrı pipeline yaradır.
// Konfiqurasiya Jenkins UI-dan olur:
//
// 1. New Item -> Multibranch Pipeline
// 2. Branch Sources -> Git -> Repository URL
// 3. Build Configuration -> Jenkinsfile path
// 4. Scan triggers -> 1 minute interval

// Jenkinsfile-da branch-a görə behavior
pipeline {
    agent any
    stages {
        stage('Deploy') {
            when {
                expression {
                    return env.BRANCH_NAME ==~ /(main|develop|release\/.*)/
                }
            }
            steps {
                script {
                    def target = [
                        'main': 'production',
                        'develop': 'staging'
                    ]
                    def env = target[env.BRANCH_NAME] ?: 'preview'
                    sh "./deploy.sh ${env}"
                }
            }
        }
    }
}
```

## PHP/Laravel ilə İstifadə

### Jenkins Docker Agent ilə Laravel

```groovy
pipeline {
    agent {
        docker {
            image 'php:8.3-cli'
            args '''
                -v /tmp/composer-cache:/root/.composer
                --network=ci-network
            '''
        }
    }

    stages {
        stage('Setup') {
            steps {
                sh '''
                    apt-get update && apt-get install -y \
                        git unzip libzip-dev libpng-dev
                    docker-php-ext-install pdo_mysql zip gd
                    curl -sS https://getcomposer.org/installer | php
                    mv composer.phar /usr/local/bin/composer
                '''
            }
        }

        stage('Install & Test') {
            steps {
                sh '''
                    composer install --no-interaction
                    cp .env.testing .env
                    php artisan key:generate
                    php artisan test
                '''
            }
        }
    }
}
```

### Docker Compose ilə Jenkins Pipeline

```groovy
stage('Integration Test') {
    steps {
        sh '''
            docker compose -f docker-compose.ci.yml up -d
            docker compose -f docker-compose.ci.yml exec -T app php artisan migrate
            docker compose -f docker-compose.ci.yml exec -T app php artisan test
        '''
    }
    post {
        always {
            sh 'docker compose -f docker-compose.ci.yml down -v'
        }
    }
}
```

## Interview Sualları

### Q1: Jenkins Master və Agent arasında fərq nədir?
**Cavab:** Master (Controller) pipeline-ları schedule edir, konfigurasiyanı saxlayır, UI təmin edir və agent-ları idarə edir. Agent (Node) isə əsl build işlərini icra edir. Master-ə yük düşməsin deyə build-lər agent-larda işlədilməlidir. Agent-lar SSH və ya JNLP ilə master-ə qoşulur.

### Q2: Declarative və Scripted pipeline fərqi nədir?
**Cavab:** Declarative pipeline strukturlaşdırılmış syntax ilə yazılır, öyrənməsi asandır, `pipeline {}` bloku ilə başlayır. Scripted pipeline tam Groovy scripting gücü verir, daha çevikdir, `node {}` ilə başlayır. Yeni layihələr üçün Declarative tövsiyə olunur, kompleks logic üçün Scripted daha uyğundur.

### Q3: Shared Library nədir?
**Cavab:** Shared Library müxtəlif pipeline-lar arasında paylaşılan Groovy kodudur. Ayrı Git repository-də saxlanılır. `vars/` qovluğunda global functions, `src/` qovluğunda class-lar olur. `@Library` annotation ilə Jenkinsfile-da import olunur. DRY prinsipini təmin edir.

### Q4: Jenkins-i necə scale edərsiniz?
**Cavab:** Distributed builds üçün çoxlu agent əlavə etmək, Docker agent ilə dynamic scaling, Kubernetes plugin ilə pod-based agents, master-da build etməmək, agent-ları label ilə qruplaşdırmaq, build artifact-ları external storage-da saxlamaq.

### Q5: Jenkins vs GitHub Actions - hansını seçmək?
**Cavab:** Jenkins: on-premise, tam kontrol, 1800+ plugin, enterprise compliance, complex workflows. GitHub Actions: SaaS, GitHub ilə tight integration, marketplace, daha asan setup, managed infrastructure. Kiçik komandalar üçün GitHub Actions, enterprise üçün Jenkins daha uyğundur.

### Q6: Jenkins security best practices?
**Cavab:** Matrix-based security, Role-based access control, credentials-ı Jenkins Credentials Store-da saxlamaq, agent-ları isolated network-da saxlamaq, audit logging enable etmək, plugin-ləri aktual saxlamaq, CSRF protection, script approval.

## Best Practices

1. **Pipeline as Code** - Jenkinsfile repository-də saxlanılmalıdır
2. **Declarative Pipeline** - Mümkün olduqda declarative syntax istifadə edin
3. **Shared Libraries** - Təkrarlanan kodu library-ə çıxarın
4. **Agent Labels** - Build-ləri uyğun agent-lara yönləndirin
5. **Credentials Plugin** - Secrets-ı heç vaxt pipeline-da hardcode etməyin
6. **Parallel Stages** - Müstəqil stage-ları paralel işlədin
7. **Build Artifacts** - Artifact-ları archive edin, workspace-i təmizləyin
8. **Notifications** - Build nəticələrini Slack/email ilə bildirin
9. **Backup** - Jenkins home directory-ni mütəmadi backup edin
10. **Plugin Management** - Yalnız lazımi pluginləri quraşdırın, aktual saxlayın
