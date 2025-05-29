// Конфигурация для development сервера
module.exports = {
  // Порт для Node.js сервера
  port: 3000,
  
  // URL вашего PHP сервера (XAMPP/WAMP/MAMP)
  phpServerUrl: 'http://localhost:80',
  
  // Альтернативные настройки для разных окружений
  environments: {
    xampp: 'http://localhost:80',
    wamp: 'http://localhost:80', 
    mamp: 'http://localhost:8888',
    custom: 'http://localhost:8080'
  },
  
  // Файлы для отслеживания изменений (live reload)
  watchPaths: [
    '**/*.php',
    '**/*.js', 
    '**/*.css',
    '**/*.html',
    '**/*.sql'
  ],
  
  // Игнорируемые пути
  ignorePaths: [
    'node_modules/**',
    '.git/**',
    'server.js',
    'dev-config.js',
    '**/*.log'
  ],
  
  // Настройки прокси
  proxy: {
    timeout: 30000,
    changeOrigin: true,
    logLevel: 'info'
  },
  
  // Настройки live reload
  liveReload: {
    enabled: true,
    delay: 100 // задержка перед перезагрузкой (мс)
  }
}; 