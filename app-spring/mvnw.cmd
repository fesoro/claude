@REM Maven Wrapper for Windows
@echo off
setlocal
set "MAVEN_PROJECTBASEDIR=%~dp0"
set "WRAPPER_JAR=%MAVEN_PROJECTBASEDIR%.mvn\wrapper\maven-wrapper.jar"

if not exist "%WRAPPER_JAR%" (
  echo Maven wrapper jar tapilmadi: %WRAPPER_JAR% — internet baglantisi ile ilk run zamani avtomatik yuklenecek
)

"%JAVA_HOME%\bin\java" -classpath "%WRAPPER_JAR%" -Dmaven.multiModuleProjectDirectory="%MAVEN_PROJECTBASEDIR%" org.apache.maven.wrapper.MavenWrapperMain %*
