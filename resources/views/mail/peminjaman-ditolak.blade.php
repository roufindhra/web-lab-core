<!DOCTYPE html>
<html lang="id">
<body style="margin: 0; padding: 24px; background-color: #f3f4f6; font-family: Arial, Helvetica, sans-serif; color: #111827;">
    <div style="max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; padding: 24px; border: 1px solid #e5e7eb;">
        <h1 style="font-size: 18px; margin: 0 0 4px;">{{ $peminjaman->keperluan->value === 'penyewaan_komersil' ? 'Pengajuan Sewa Alat Ditolak' : 'Pengajuan Peminjaman Alat Ditolak' }}</h1>
        <p style="font-size: 13px; color: #6b7280; margin: 0 0 20px;">
            Nomor pengajuan <strong>#{{ $peminjaman->id }}</strong>
        </p>

        <p style="font-size: 14px; margin: 0 0 16px;">
            Halo {{ $peminjaman->nama }}, mohon maaf pengajuan Anda <strong>tidak dapat disetujui</strong>.
        </p>

        @if ($peminjaman->tanggal_pinjam !== null && $peminjaman->tanggal_kembali !== null)
            <table style="width: 100%; font-size: 13px; border-collapse: collapse; margin-bottom: 16px;">
                <tr>
                    <td style="padding: 4px 0; color: #6b7280; width: 40%;">Tanggal Pinjam</td>
                    <td style="padding: 4px 0;">{{ $peminjaman->tanggal_pinjam->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; color: #6b7280;">Tanggal Kembali</td>
                    <td style="padding: 4px 0;">{{ $peminjaman->tanggal_kembali->format('d M Y') }}</td>
                </tr>
            </table>
        @endif

        <p style="font-size: 13px; color: #6b7280; margin: 0 0 4px;">Alasan:</p>
        <p style="font-size: 14px; margin: 0 0 16px; padding: 10px 12px; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 6px;">
            {{ $peminjaman->catatan }}
        </p>

        @if ($items->isNotEmpty())
            <table style="width: 100%; font-size: 13px; border-collapse: collapse; margin-bottom: 16px;">
                <thead>
                    <tr style="background-color: #f9fafb;">
                        <th style="text-align: left; padding: 6px 8px; border: 1px solid #e5e7eb;">Alat</th>
                        <th style="text-align: left; padding: 6px 8px; border: 1px solid #e5e7eb;">Inventaris</th>
                        <th style="text-align: right; padding: 6px 8px; border: 1px solid #e5e7eb;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td style="padding: 6px 8px; border: 1px solid #e5e7eb;">{{ $item->alat->nama_alat }}</td>
                            <td style="padding: 6px 8px; border: 1px solid #e5e7eb; font-family: monospace;">{{ $item->inventaris }}</td>
                            <td style="padding: 6px 8px; border: 1px solid #e5e7eb; text-align: right;">{{ $item->jumlah }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p style="font-size: 13px; color: #6b7280; margin: 0;">
            Anda dipersilakan mengajukan kembali dengan jadwal atau alat yang berbeda. Untuk pertanyaan lebih lanjut,
            hubungi narahubung laboratorium di <a href="mailto:{{ $narahubung }}" style="color: #111827;">{{ $narahubung }}</a>.
        </p>
    </div>
</body>
</html>
