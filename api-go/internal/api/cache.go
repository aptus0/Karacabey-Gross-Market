package api

import (
	"sync"
	"time"
)

type cacheEntry struct {
	value     any
	expiresAt time.Time
}

type TTLCache struct {
	mu  sync.RWMutex
	ttl time.Duration
	m   map[string]cacheEntry
}

func NewTTLCache(ttl time.Duration) *TTLCache {
	if ttl <= 0 {
		ttl = 30 * time.Second
	}
	return &TTLCache{ttl: ttl, m: make(map[string]cacheEntry)}
}

func (c *TTLCache) Get(key string) (any, bool) {
	c.mu.RLock()
	entry, ok := c.m[key]
	c.mu.RUnlock()
	if !ok || time.Now().After(entry.expiresAt) {
		if ok {
			c.mu.Lock()
			delete(c.m, key)
			c.mu.Unlock()
		}
		return nil, false
	}
	return entry.value, true
}

func (c *TTLCache) Set(key string, value any) {
	c.mu.Lock()
	c.m[key] = cacheEntry{value: value, expiresAt: time.Now().Add(c.ttl)}
	if len(c.m) > 2000 {
		now := time.Now()
		for k, v := range c.m {
			if now.After(v.expiresAt) {
				delete(c.m, k)
			}
		}
	}
	c.mu.Unlock()
}

func cached[T any](c *TTLCache, key string, loader func() (T, error)) (T, error) {
	if value, ok := c.Get(key); ok {
		if typed, ok := value.(T); ok {
			return typed, nil
		}
	}
	loaded, err := loader()
	if err != nil {
		var zero T
		return zero, err
	}
	c.Set(key, loaded)
	return loaded, nil
}

func (c *TTLCache) PurgePrefix(prefix string) {
	c.mu.Lock()
	for key := range c.m {
		if len(key) >= len(prefix) && key[:len(prefix)] == prefix {
			delete(c.m, key)
		}
	}
	c.mu.Unlock()
}
