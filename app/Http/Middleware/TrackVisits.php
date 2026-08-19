<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\PageVisit;

class TrackVisits
{
    /**
     * FIX-01: Lista de firmas de User-Agent de bots conocidos.
     *
     * Cubre: search engines, herramientas SEO, crawlers de IA,
     * librerías HTTP genéricas y clientes de API automatizados.
     * Todas las firmas están en minúsculas; la comparación se hace
     * con strtolower() para ser case-insensitive sin overhead extra.
     */
    private const BOT_SIGNATURES = [
        // ── Search Engines ───────────────────────────────────────────
        'googlebot', 'google-inspectiontool', 'googlelighthouse',
        'bingbot', 'msnbot', 'bingpreview',
        'slurp',                    // Yahoo
        'duckduckbot',
        'baiduspider',
        'yandexbot', 'yandeximages', 'yandexmedia',
        'sogou',
        'exabot',
        'ia_archiver',              // Wayback Machine
        'applebot',
        'naverbot', 'yeti',         // Naver (Korea)
        'petalbot',                 // Huawei
        'twitterbot',
        'facebot', 'facebookexternalhit',
        'linkedinbot',

        // ── Herramientas SEO / Auditoría ─────────────────────────────
        'ahrefsbot',
        'semrushbot',
        'dotbot',                   // Moz
        'rogerbot',                 // Moz
        'mj12bot',                  // Majestic
        'blexbot',
        'seokicks',
        'screaming frog',

        // ── Crawlers de IA / LLM ─────────────────────────────────────
        'gptbot',                   // OpenAI
        'chatgpt-user',
        'claudebot',                // Anthropic
        'anthropic-ai',
        'ccbot',                    // CommonCrawl
        'bytespider',               // TikTok / ByteDance
        'dataforseobot',

        // ── Librerías HTTP / CLIs ─────────────────────────────────────
        'curl/',
        'wget/',
        'python-requests',
        'python-urllib',
        'go-http-client',
        'java/',
        'okhttp',
        'libwww-perl',
        'lwp-trivial',

        // ── Clientes de API / Testing ─────────────────────────────────
        'postmanruntime',
        'insomnia/',
        'axios/',
        'node-fetch',
        'node-http',
        'got/',                     // Node.js got library
        'undici',                   // Node.js fetch underlying
        'httpie',
        'pycurl',

        // ── Monitores, Scanners y Botnets Recientes ─────────────────
        'internetmeasurement',
        'cms-checker',
        'google-read-aloud',
        'oai-searchbot',
        'palo alto networks',
        'cortex-xpanse',
        'iphone os 13_2_3',         // Botnet/Scanner genérico muy ruidoso
        'iphone os 18_7_8',         // Botnet/Scanner detectado en logs
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && !$request->ajax() && !$request->header('X-Livewire')) {

            // FIX-01: Filtrar bots ANTES de cualquier consulta a la DB.
            if (!$this->isBot($request)) {

                // No contar visitas de administradores — evita que la navegación
                // por el panel de admin infle el contador de visitas de la tienda.
                $user = $request->user();
                if ($user && $user->isAdmin()) {
                    return $next($request);
                }

                $today = now()->format('Y-m-d');

                if ($request->session()->get('last_visit_date') !== $today) {
                    try {
                        $alreadyVisited = PageVisit::where('ip_address', $request->ip())
                                                   ->whereDate('created_at', now()->toDateString())
                                                   ->exists();

                        if (!$alreadyVisited) {
                            PageVisit::create([
                                'ip_address' => $request->ip(),
                                'url'        => $request->path(),
                                'user_agent' => $request->userAgent(),
                            ]);
                        }

                        $request->session()->put('last_visit_date', $today);
                    } catch (\Exception $e) {
                        // No interrumpir la petición por errores de tracking.
                    }
                }
            }
        }

        return $next($request);
    }

    /**
     * Determina si el request proviene de un bot o herramienta automatizada.
     *
     * La detección se basa en coincidencia de subcadenas del User-Agent contra
     * una lista curada de firmas conocidas (BOT_SIGNATURES). La comparación
     * es case-insensitive y se ejecuta antes de cualquier acceso a la DB.
     *
     * Un User-Agent vacío o nulo se trata siempre como bot, ya que los
     * navegadores reales siempre envían esta cabecera.
     */
    private function isBot(Request $request): bool
    {
        $userAgent = $request->userAgent();

        // Sin User-Agent → definitivamente automatizado
        if (empty($userAgent)) {
            return true;
        }

        $ua = strtolower($userAgent);

        foreach (self::BOT_SIGNATURES as $signature) {
            if (str_contains($ua, $signature)) {
                return true;
            }
        }

        return false;
    }
}
