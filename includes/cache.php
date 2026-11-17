<?php
/**
 * 简单文件缓存系统
 * 适用于虚拟主机环境的轻量级缓存
 */

class SimpleCache {
    private $cache_dir;
    private $default_lifetime;
    
    public function __construct($cache_dir = 'cache', $default_lifetime = 3600) {
        $this->cache_dir = __DIR__ . '/../' . $cache_dir;
        $this->default_lifetime = $default_lifetime;
        
        // 确保缓存目录存在
        if (!is_dir($this->cache_dir)) {
            @mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * 获取缓存
     */
    public function get($key) {
        if (!CACHE_ENABLED) {
            return null;
        }
        
        $file = $this->getCacheFile($key);
        if (!file_exists($file)) {
            return null;
        }
        
        $data = file_get_contents($file);
        if ($data === false) {
            return null;
        }
        
        $cache_data = json_decode($data, true);
        if (!$cache_data || !isset($cache_data['expires'], $cache_data['data'])) {
            @unlink($file);
            return null;
        }
        
        // 检查是否过期
        if (time() > $cache_data['expires']) {
            @unlink($file);
            return null;
        }
        
        return $cache_data['data'];
    }
    
    /**
     * 设置缓存
     */
    public function set($key, $data, $lifetime = null) {
        if (!CACHE_ENABLED) {
            return false;
        }
        
        $lifetime = $lifetime ?: $this->default_lifetime;
        $expires = time() + $lifetime;
        
        $cache_data = [
            'expires' => $expires,
            'data' => $data
        ];
        
        $file = $this->getCacheFile($key);
        return file_put_contents($file, json_encode($cache_data)) !== false;
    }
    
    /**
     * 删除缓存
     */
    public function delete($key) {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }
    
    /**
     * 清空所有缓存
     */
    public function clear() {
        $files = glob($this->cache_dir . '/*.cache');
        $deleted = 0;
        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
        return $deleted;
    }
    
    /**
     * 清理过期缓存
     */
    public function cleanup() {
        $files = glob($this->cache_dir . '/*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            $data = file_get_contents($file);
            if ($data) {
                $cache_data = json_decode($data, true);
                if ($cache_data && isset($cache_data['expires'])) {
                    if (time() > $cache_data['expires']) {
                        if (@unlink($file)) {
                            $deleted++;
                        }
                    }
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * 获取缓存文件路径
     */
    private function getCacheFile($key) {
        $safe_key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cache_dir . '/' . $safe_key . '.cache';
    }
}

// 创建全局缓存实例
$cache = new SimpleCache('cache', CACHE_LIFETIME);

/**
 * 便捷的缓存函数
 */
function cache_get($key) {
    global $cache;
    return $cache->get($key);
}

function cache_set($key, $data, $lifetime = null) {
    global $cache;
    return $cache->set($key, $data, $lifetime);
}

function cache_delete($key) {
    global $cache;
    return $cache->delete($key);
}

function cache_clear() {
    global $cache;
    return $cache->clear();
}
?>