<?php

namespace App\Mail;

use App\Models\HargaSewa;
use App\Models\Peminjaman;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SewaDisetujui extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Peminjaman $peminjaman,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengajuan Sewa Alat Disetujui',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.sewa-disetujui',
            with: [
                'peminjaman' => $this->peminjaman,
                'items' => $this->peminjaman->items()->with('alat')->get(),
                'jumlahHari' => $this->jumlahHari(),
                'totalEstimasi' => $this->totalEstimasi(),
            ],
        );
    }

    private function jumlahHari(): int
    {
        if ($this->peminjaman->tanggal_pinjam === null || $this->peminjaman->tanggal_kembali === null) {
            return 0;
        }

        return max(1, (int) Carbon::parse($this->peminjaman->tanggal_pinjam)
            ->startOfDay()
            ->diffInDays(Carbon::parse($this->peminjaman->tanggal_kembali)->startOfDay()) + 1);
    }

    private function totalEstimasi(): float
    {
        $tarif = HargaSewa::query()->pluck('tarif_per_hari', 'nama_alat');

        return round($this->peminjaman->items->sum(
            fn ($item) => $item->jumlah * $this->jumlahHari() * (float) $tarif->get($item->alat->nama_alat, 0),
        ), 2);
    }
}
