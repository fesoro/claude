// Package api — standart HTTP response wrapper
//
// Laravel: ApiResponse.php
// Spring: ApiResponse.java (record)
// Go: struct + helper functions
package api

type Response struct {
	Success bool              `json:"success"`
	Message string            `json:"message,omitempty"`
	Data    any               `json:"data,omitempty"`
	Meta    map[string]any    `json:"meta,omitempty"`
	Errors  map[string]string `json:"errors,omitempty"`
}

func Success(data any) Response {
	return Response{Success: true, Data: data}
}

func SuccessWithMessage(data any, msg string) Response {
	return Response{Success: true, Message: msg, Data: data}
}

func Paginated(data any, meta map[string]any) Response {
	return Response{Success: true, Data: data, Meta: meta}
}

func Error(msg string) Response {
	return Response{Success: false, Message: msg}
}

func ValidationFailed(errors map[string]string) Response {
	return Response{Success: false, Message: "Validasiya xətası", Errors: errors}
}
