# Mail DNS and authentication

Outgoing mail is sent directly from `195.87.234.152` and signed with the
`kgm2026` DKIM selector. Publish these DNS records before sending mail to
Gmail, Yahoo, or other external providers.

| Type | Name | Value | Cloudflare proxy |
| --- | --- | --- | --- |
| TXT | `@` | `v=spf1 ip4:195.87.234.152 -all` | N/A |
| TXT | `kgm2026._domainkey` | `v=DKIM1; h=sha256; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAucQ4u9RK1Gjb6ambjIXqCOOnXJIlAhgAP8qYirU2dTVKS6gi72g0uGqwn+IsIj/uTm+XermjPfA1Q4D51NenfgTudTmy7wxdPbD3gm77MwsUI10Yx/ZgJWwy52r4GtiqJll2tYsK99lJ3hZcyj2VH4uBGMhJa61bp/Ufwskcei3umlUEF3dDQ0EBD9ls644mbTpXPIN+bgC7lb4H4E0rrB4cdj7mGZbDwpAI7CfO379SJGsOomnOhWBWabJNJkc5fvill7mWyZIRGb+cdWDxyEcOhWcU2lCXeeI9qhpIOvh4kg7UDEtr9G87MVkCcP4KST48sSZNpigOtx89f5EvYwIDAQAB` | N/A |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:postmaster@karacabeygrossmarket.com; adkim=s; aspf=s` | N/A |
| MX | `@` | `mail.karacabeygrossmarket.com` (priority `10`) | N/A |
| A | `mail` | `195.87.234.152` | DNS only |
| CNAME | `webmail` | Cloudflare Tunnel hedefi | Proxied / Tunnel |

The server provider must also set the reverse DNS/PTR for `195.87.234.152` to
`mail.karacabeygrossmarket.com`.

Cloudflare Tunnel cannot carry normal SMTP, IMAP, or submission traffic.
Do not leave `mail.karacabeygrossmarket.com` as a proxied or Tunnel CNAME when
using it as the MX host. The production config now uses this split:

- `mail.karacabeygrossmarket.com`: SMTP/IMAP/MX host, **A record DNS only**.
- `webmail.karacabeygrossmarket.com`: HTTP mail panel, Cloudflare Tunnel route.

Create/repair the HTTP panel route with:

```bash
cloudflared tunnel route dns 204f80e0-c1c6-4209-9645-c8fff609f047 webmail.karacabeygrossmarket.com
```

Verify the public records:

```bash
dig +short TXT karacabeygrossmarket.com
dig +short TXT kgm2026._domainkey.karacabeygrossmarket.com
dig +short TXT _dmarc.karacabeygrossmarket.com
dig +short MX karacabeygrossmarket.com
dig +short A mail.karacabeygrossmarket.com
dig +short CNAME webmail.karacabeygrossmarket.com
dig +short -x 195.87.234.152
openssl s_client -starttls smtp -connect mail.karacabeygrossmarket.com:587 -servername mail.karacabeygrossmarket.com </dev/null
```
