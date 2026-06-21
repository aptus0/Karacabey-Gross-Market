package api

import (
	"sort"
	"strings"
	"sync"
	"time"
)

type RuntimeMetrics struct {
	mu             sync.RWMutex
	startedAt      time.Time
	totalRequests  int64
	totalLatency   time.Duration
	statusCounters map[int]int64
	routeCounters  map[string]int64
	slowCounters   map[string]int64
	errorCounters  map[string]int64
	lastRequests   []RequestSample
}

type RequestSample struct {
	At         time.Time `json:"at"`
	Method     string    `json:"method"`
	Path       string    `json:"path"`
	Status     int       `json:"status"`
	DurationMS int64     `json:"duration_ms"`
	IP         string    `json:"ip,omitempty"`
}

func NewRuntimeMetrics() *RuntimeMetrics {
	return &RuntimeMetrics{
		startedAt:      time.Now().UTC(),
		statusCounters: map[int]int64{},
		routeCounters:  map[string]int64{},
		slowCounters:   map[string]int64{},
		errorCounters:  map[string]int64{},
		lastRequests:   make([]RequestSample, 0, 80),
	}
}

func (m *RuntimeMetrics) Observe(method, rawPath string, status int, duration time.Duration, ip string) {
	if m == nil {
		return
	}
	if status == 0 {
		status = 200
	}
	path := normalizeMetricPath(rawPath)
	sample := RequestSample{At: time.Now().UTC(), Method: method, Path: path, Status: status, DurationMS: duration.Milliseconds(), IP: ip}

	m.mu.Lock()
	defer m.mu.Unlock()
	m.totalRequests++
	m.totalLatency += duration
	m.statusCounters[status]++
	m.routeCounters[method+" "+path]++
	if duration >= 750*time.Millisecond {
		m.slowCounters[method+" "+path]++
	}
	if status >= 500 {
		m.errorCounters[method+" "+path]++
	}
	m.lastRequests = append(m.lastRequests, sample)
	if len(m.lastRequests) > 80 {
		copy(m.lastRequests, m.lastRequests[len(m.lastRequests)-80:])
		m.lastRequests = m.lastRequests[:80]
	}
}

func (m *RuntimeMetrics) Snapshot() map[string]any {
	m.mu.RLock()
	defer m.mu.RUnlock()

	avgMS := int64(0)
	if m.totalRequests > 0 {
		avgMS = m.totalLatency.Milliseconds() / m.totalRequests
	}

	status := make(map[string]int64, len(m.statusCounters))
	for code, count := range m.statusCounters {
		status[intToString(code)] = count
	}

	last := make([]RequestSample, len(m.lastRequests))
	copy(last, m.lastRequests)
	sort.Slice(last, func(i, j int) bool { return last[i].At.After(last[j].At) })

	return map[string]any{
		"started_at":        m.startedAt,
		"uptime_seconds":    int64(time.Since(m.startedAt).Seconds()),
		"total_requests":    m.totalRequests,
		"avg_latency_ms":    avgMS,
		"status_counters":   status,
		"top_routes":        topCounters(m.routeCounters, 20),
		"slow_routes":       topCounters(m.slowCounters, 20),
		"error_routes":      topCounters(m.errorCounters, 20),
		"recent_requests":   last,
		"slow_threshold_ms": 750,
	}
}

func topCounters(src map[string]int64, limit int) []map[string]any {
	items := make([]map[string]any, 0, len(src))
	for key, count := range src {
		items = append(items, map[string]any{"key": key, "count": count})
	}
	sort.Slice(items, func(i, j int) bool { return items[i]["count"].(int64) > items[j]["count"].(int64) })
	if len(items) > limit {
		return items[:limit]
	}
	return items
}

func normalizeMetricPath(path string) string {
	if path == "" {
		return "/"
	}
	parts := strings.Split(path, "/")
	for i, part := range parts {
		if part == "" {
			continue
		}
		if looksLikeID(part) {
			parts[i] = "{id}"
		}
	}
	joined := strings.Join(parts, "/")
	if strings.HasPrefix(joined, "/api/v1/products/") && joined != "/api/v1/products/suggest" {
		return "/api/v1/products/{slug}"
	}
	if strings.HasPrefix(joined, "/api/v1/content/campaigns/") {
		return "/api/v1/content/campaigns/{slug}"
	}
	if strings.HasPrefix(joined, "/api/v1/content/pages/") {
		return "/api/v1/content/pages/{slug}"
	}
	return joined
}

func looksLikeID(value string) bool {
	if len(value) >= 16 {
		return true
	}
	allDigits := true
	for _, ch := range value {
		if ch < '0' || ch > '9' {
			allDigits = false
			break
		}
	}
	return allDigits && len(value) > 0
}

func intToString(value int) string {
	if value == 0 {
		return "0"
	}
	neg := value < 0
	if neg {
		value = -value
	}
	buf := [20]byte{}
	i := len(buf)
	for value > 0 {
		i--
		buf[i] = byte('0' + value%10)
		value /= 10
	}
	if neg {
		i--
		buf[i] = '-'
	}
	return string(buf[i:])
}
