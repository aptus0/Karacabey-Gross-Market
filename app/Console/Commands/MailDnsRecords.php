<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MailDnsRecords extends Command
{
    protected $signature = 'kgm:mail-dns-records
        {--domain=karacabeygrossmarket.com : Ana domain}
        {--mail-ip= : mail.domain için DNS only A kaydı IP adresi}
        {--webmail-target= : webmail.domain için Cloudflare Tunnel CNAME hedefi}
        {--dkim-selector=default : DKIM selector}';

    protected $description = 'Cloudflare üzerinde açılması gereken mail/webmail DNS kayıtlarını üretir.';

    public function handle(): int
    {
        $domain = trim((string) $this->option('domain')) ?: 'karacabeygrossmarket.com';
        $mailIp = trim((string) ($this->option('mail-ip') ?: env('MAIL_SERVER_IPV4', 'SUNUCU_IP_ADRESI')));
        $webmailTarget = trim((string) ($this->option('webmail-target') ?: env('WEBMAIL_TUNNEL_TARGET', 'cloudflare-tunnel-id.cfargotunnel.com')));
        $selector = trim((string) $this->option('dkim-selector')) ?: 'default';

        $records = [
            ['A', 'mail.' . $domain, $mailIp, 'DNS only', 'SMTP/IMAP burada çalışır. Cloudflare proxy kapalı olmalı.'],
            ['MX', $domain, '10 mail.' . $domain, 'DNS only', 'Alan adının gelen postası mail hostuna gider.'],
            ['TXT', $domain, 'v=spf1 mx a:mail.' . $domain . ' ~all', 'DNS only', 'Gönderen IP doğrulaması.'],
            ['TXT', $selector . '._domainkey.' . $domain, 'v=DKIM1; k=rsa; p=DKIM_PUBLIC_KEY', 'DNS only', 'DKIM public key ile değiştirilecek.'],
            ['TXT', '_dmarc.' . $domain, 'v=DMARC1; p=quarantine; rua=mailto:support@' . $domain . '; adkim=s; aspf=s', 'DNS only', 'Canlı sonrası p=reject seviyesine alınabilir.'],
            ['CNAME', 'webmail.' . $domain, $webmailTarget, 'Proxied / Tunnel', 'Sadece web arayüzü. SMTP için kullanılmaz.'],
        ];

        $this->info('Cloudflare DNS kayıtları');
        $this->table(['Type', 'Name', 'Value', 'Mode', 'Not'], $records);
        $this->warn('PTR/rDNS kaydı DNS panelinden değil, sunucu/IP sağlayıcısından mail.' . $domain . ' olarak ayarlanmalı.');
        $this->warn('mail.' . $domain . ' kesinlikle turuncu bulut/proxy olmamalı; DNS only kalmalı.');

        return self::SUCCESS;
    }
}
