// Package channel — Email/SMS notification channels
package channel

import (
	"bytes"
	"log/slog"
	"text/template"

	mail "github.com/wneessen/go-mail"
)

type EmailChannel struct {
	client *mail.Client
	from   string
}

func NewEmailChannel(host string, port int, from string) (*EmailChannel, error) {
	client, err := mail.NewClient(host,
		mail.WithPort(port),
		mail.WithTLSPolicy(mail.NoTLS),  // Mailpit local üçün
	)
	if err != nil {
		return nil, err
	}
	return &EmailChannel{client: client, from: from}, nil
}

// Send — Go template istifadə edir (Thymeleaf əvəzi)
func (e *EmailChannel) Send(to, subject, templateBody string, data map[string]any) error {
	tmpl, err := template.New("email").Parse(templateBody)
	if err != nil {
		return err
	}
	var buf bytes.Buffer
	if err := tmpl.Execute(&buf, data); err != nil {
		return err
	}

	msg := mail.NewMsg()
	if err := msg.From(e.from); err != nil {
		return err
	}
	if err := msg.To(to); err != nil {
		return err
	}
	msg.Subject(subject)
	msg.SetBodyString(mail.TypeTextHTML, buf.String())

	if err := e.client.DialAndSend(msg); err != nil {
		slog.Error("email göndərmə xətası", "to", to, "err", err)
		return err
	}
	slog.Info("email göndərildi", "to", to, "subject", subject)
	return nil
}
