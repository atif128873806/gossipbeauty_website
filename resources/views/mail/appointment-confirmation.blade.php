<p>Hello {{ $details['first_name'] }},</p>
<p>Your appointment at Gossips Beauty is {{ ($details['notice_type'] ?? '') === 'appointment.cancelled' ? 'cancelled' : ((($details['notice_type'] ?? '') === 'appointment.changed') ? 'updated' : 'confirmed') }}.</p>
<p><strong>Reference:</strong> {{ $details['reference'] }}<br>
<strong>Service:</strong> {{ implode(', ', $details['services']) }}<br>
<strong>Professional:</strong> {{ $details['staff'] ?? 'To be assigned' }}<br>
<strong>Time:</strong> {{ $details['time'] }}<br>
<strong>Studio:</strong> {{ $details['address'] }}</p>
<p>Please contact the studio directly if you need to change this appointment.</p>
