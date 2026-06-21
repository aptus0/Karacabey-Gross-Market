package api

import (
	"bufio"
	"context"
	"errors"
	"fmt"
	"io"
	"net"
	"strconv"
	"strings"
	"time"
)

type RedisClient struct {
	addr     string
	password string
	db       int
	timeout  time.Duration
}

func NewRedisClient(addr, password string, db int, timeout time.Duration) *RedisClient {
	addr = strings.TrimSpace(addr)
	if addr == "" {
		return nil
	}
	if timeout <= 0 {
		timeout = 800 * time.Millisecond
	}
	return &RedisClient{addr: addr, password: password, db: db, timeout: timeout}
}

func (r *RedisClient) Enabled() bool { return r != nil && r.addr != "" }

func (r *RedisClient) Ping(ctx context.Context) error {
	if !r.Enabled() {
		return nil
	}
	_, err := r.command(ctx, "PING")
	return err
}

func (r *RedisClient) Get(ctx context.Context, key string) ([]byte, bool, error) {
	if !r.Enabled() || strings.TrimSpace(key) == "" {
		return nil, false, nil
	}
	data, err := r.command(ctx, "GET", key)
	if errors.Is(err, redisNil) {
		return nil, false, nil
	}
	if err != nil {
		return nil, false, err
	}
	return data, true, nil
}

func (r *RedisClient) SetEX(ctx context.Context, key string, ttl time.Duration, value []byte) error {
	if !r.Enabled() || strings.TrimSpace(key) == "" || len(value) == 0 {
		return nil
	}
	seconds := int(ttl.Seconds())
	if seconds <= 0 {
		seconds = 60
	}
	_, err := r.command(ctx, "SETEX", key, strconv.Itoa(seconds), string(value))
	return err
}

func (r *RedisClient) Del(ctx context.Context, keys ...string) error {
	if !r.Enabled() || len(keys) == 0 {
		return nil
	}
	args := make([]string, 0, len(keys)+1)
	args = append(args, "DEL")
	for _, key := range keys {
		key = strings.TrimSpace(key)
		if key != "" {
			args = append(args, key)
		}
	}
	if len(args) == 1 {
		return nil
	}
	_, err := r.command(ctx, args[0], args[1:]...)
	return err
}

var redisNil = errors.New("redis nil")

func (r *RedisClient) command(ctx context.Context, command string, args ...string) ([]byte, error) {
	if !r.Enabled() {
		return nil, nil
	}
	dialer := net.Dialer{Timeout: r.timeout}
	conn, err := dialer.DialContext(ctx, "tcp", r.addr)
	if err != nil {
		return nil, err
	}
	defer conn.Close()
	_ = conn.SetDeadline(time.Now().Add(r.timeout))
	reader := bufio.NewReader(conn)
	if r.password != "" {
		if _, err := r.writeAndRead(conn, reader, "AUTH", r.password); err != nil {
			return nil, err
		}
	}
	if r.db > 0 {
		if _, err := r.writeAndRead(conn, reader, "SELECT", strconv.Itoa(r.db)); err != nil {
			return nil, err
		}
	}
	return r.writeAndRead(conn, reader, command, args...)
}

func (r *RedisClient) writeAndRead(conn net.Conn, reader *bufio.Reader, command string, args ...string) ([]byte, error) {
	parts := append([]string{strings.ToUpper(command)}, args...)
	var b strings.Builder
	b.WriteString("*")
	b.WriteString(strconv.Itoa(len(parts)))
	b.WriteString("\r\n")
	for _, part := range parts {
		b.WriteString("$")
		b.WriteString(strconv.Itoa(len(part)))
		b.WriteString("\r\n")
		b.WriteString(part)
		b.WriteString("\r\n")
	}
	if _, err := io.WriteString(conn, b.String()); err != nil {
		return nil, err
	}
	return readRedisReply(reader)
}

func readRedisReply(reader *bufio.Reader) ([]byte, error) {
	prefix, err := reader.ReadByte()
	if err != nil {
		return nil, err
	}
	line, err := reader.ReadString('\n')
	if err != nil {
		return nil, err
	}
	line = strings.TrimSuffix(strings.TrimSuffix(line, "\n"), "\r")
	switch prefix {
	case '+':
		return []byte(line), nil
	case '-':
		return nil, fmt.Errorf("redis error: %s", line)
	case ':':
		return []byte(line), nil
	case '$':
		length, err := strconv.Atoi(line)
		if err != nil {
			return nil, err
		}
		if length == -1 {
			return nil, redisNil
		}
		buf := make([]byte, length+2)
		if _, err := io.ReadFull(reader, buf); err != nil {
			return nil, err
		}
		return buf[:length], nil
	case '*':
		return []byte(line), nil
	default:
		return nil, fmt.Errorf("unsupported redis reply: %q", prefix)
	}
}
