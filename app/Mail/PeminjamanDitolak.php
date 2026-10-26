<?php

namespace App\Mail;

use App\Enums\KeperluanPeminjaman;
use App\Models\Peminjaman;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PeminjamanDitolak extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Peminjaman $peminjaman,
    ) {}

    public function envelope(): Envelope
    {
        $subjek = $this->peminjaman->keperluan === KeperluanPeminjaman::PenyewaanKomersil
            ? 'Pengajuan Sewa Alat Ditolak'
            : 'Pengajuan Peminjaman Alat Ditolak';

        return new Envelope(subject: $subjek);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.peminjaman-ditolak',
            with: [
                'peminjaman' => $this->peminjaman,
                'items' => $this->peminjaman->items()->with('alat')->get(),
                'narahubung' => config('mail.from.address'),
            ],
        );
    }
}
