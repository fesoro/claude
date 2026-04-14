package main

import "fmt"

// ===============================================
// gRPC - MICROSERVICE ELAQESI
// ===============================================

// gRPC - Google terefinden yaradilmis, suretli RPC (Remote Procedure Call) framework
// HTTP/2 uzerinde isleyir, Protocol Buffers (protobuf) ile serialization
// REST-den suretlidir, tip tehlukesidir, bidirectional streaming destekleyir

// QURASDIRMA:
// 1. protoc kompilyatoru yukle: https://grpc.io/docs/protoc-installation/
// 2. Go plugin-leri:
//    go install google.golang.org/protobuf/cmd/protoc-gen-go@latest
//    go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@latest
// 3. Go paketleri:
//    go get google.golang.org/grpc
//    go get google.golang.org/protobuf

func main() {
	fmt.Println("gRPC ornekleri - asagidaki addimlarla ayri layihede yaradin")

	// -------------------------------------------
	// 1. PROTO FAYLI (service.proto)
	// -------------------------------------------
	protoFayl := `
// fayl: proto/service.proto

syntax = "proto3";

package istifadeci;

option go_package = "./pb";

// Mesaj tipleri (struct kimi)
message IstifadeciSorgusu {
    int32 id = 1;
}

message IstifadeciCavabi {
    int32 id = 1;
    string ad = 2;
    string email = 3;
    int32 yas = 4;
}

message IstifadeciListSorgusu {
    int32 limit = 1;
}

message BosMessaj {}

// Servis tərifi
service IstifadeciServisi {
    // Unary - bir sorgu, bir cavab
    rpc IstifadeciAlq (IstifadeciSorgusu) returns (IstifadeciCavabi);

    // Server streaming - bir sorgu, bir nece cavab
    rpc IstifadecileriSiyahila (IstifadeciListSorgusu) returns (stream IstifadeciCavabi);
}

// Kompilyasiya emri:
// protoc --go_out=. --go-grpc_out=. proto/service.proto
`

	// -------------------------------------------
	// 2. SERVER KODU
	// -------------------------------------------
	serverKodu := `
package main

import (
    "context"
    "fmt"
    "log"
    "net"

    "google.golang.org/grpc"
    pb "myproject/pb" // proto-dan yaranmis kod
)

// -------------------------------------------
// Server implementasiyasi
// -------------------------------------------
type server struct {
    pb.UnimplementedIstifadeciServisiServer // geriye uygunluq ucun
}

// Unary RPC - bir sorgu, bir cavab
func (s *server) IstifadeciAlq(ctx context.Context, req *pb.IstifadeciSorgusu) (*pb.IstifadeciCavabi, error) {
    // Real layihede database-den alinacaq
    if req.Id == 1 {
        return &pb.IstifadeciCavabi{
            Id:    1,
            Ad:    "Orkhan",
            Email: "orkhan@mail.az",
            Yas:   25,
        }, nil
    }
    return nil, fmt.Errorf("istifadeci tapilmadi: %d", req.Id)
}

// Server streaming - bir sorgu, bir nece cavab
func (s *server) IstifadecileriSiyahila(req *pb.IstifadeciListSorgusu, stream pb.IstifadeciServisi_IstifadecileriSiyahilaServer) error {
    istifadeciler := []*pb.IstifadeciCavabi{
        {Id: 1, Ad: "Orkhan", Email: "orkhan@mail.az", Yas: 25},
        {Id: 2, Ad: "Eli", Email: "eli@mail.az", Yas: 30},
        {Id: 3, Ad: "Veli", Email: "veli@mail.az", Yas: 28},
    }

    limit := int(req.Limit)
    if limit <= 0 || limit > len(istifadeciler) {
        limit = len(istifadeciler)
    }

    for i := 0; i < limit; i++ {
        if err := stream.Send(istifadeciler[i]); err != nil {
            return err
        }
    }
    return nil
}

func main() {
    // TCP listener yarat
    lis, err := net.Listen("tcp", ":50051")
    if err != nil {
        log.Fatalf("Dinleme xetasi: %v", err)
    }

    // gRPC server yarat
    grpcServer := grpc.NewServer()

    // Servisi qeydiyyatdan kecir
    pb.RegisterIstifadeciServisiServer(grpcServer, &server{})

    log.Println("gRPC server :50051 portunda isleyir...")
    if err := grpcServer.Serve(lis); err != nil {
        log.Fatalf("Server xetasi: %v", err)
    }
}
`

	// -------------------------------------------
	// 3. CLIENT KODU
	// -------------------------------------------
	clientKodu := `
package main

import (
    "context"
    "fmt"
    "io"
    "log"
    "time"

    "google.golang.org/grpc"
    "google.golang.org/grpc/credentials/insecure"
    pb "myproject/pb"
)

func main() {
    // Server-e qosul
    conn, err := grpc.NewClient("localhost:50051",
        grpc.WithTransportCredentials(insecure.NewCredentials()),
    )
    if err != nil {
        log.Fatalf("Qosulma xetasi: %v", err)
    }
    defer conn.Close()

    // Client yarat
    client := pb.NewIstifadeciServisiClient(conn)

    // Context ile timeout
    ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
    defer cancel()

    // -------------------------------------------
    // Unary call
    // -------------------------------------------
    ist, err := client.IstifadeciAlq(ctx, &pb.IstifadeciSorgusu{Id: 1})
    if err != nil {
        log.Fatalf("Sorgu xetasi: %v", err)
    }
    fmt.Printf("Tapildi: %+v\n", ist)

    // -------------------------------------------
    // Server streaming
    // -------------------------------------------
    stream, err := client.IstifadecileriSiyahila(ctx, &pb.IstifadeciListSorgusu{Limit: 10})
    if err != nil {
        log.Fatalf("Stream xetasi: %v", err)
    }

    for {
        ist, err := stream.Recv()
        if err == io.EOF {
            break // bitmis
        }
        if err != nil {
            log.Fatalf("Oxuma xetasi: %v", err)
        }
        fmt.Printf("Stream: %+v\n", ist)
    }
}
`

	fmt.Println("=== PROTO FAYLI ===")
	fmt.Println(protoFayl)
	fmt.Println("=== SERVER ===")
	fmt.Println(serverKodu)
	fmt.Println("=== CLIENT ===")
	fmt.Println(clientKodu)

	fmt.Println(`
=== gRPC vs REST ===
+-------------------+------------------+------------------+
| Xususiyyet        | gRPC             | REST             |
+-------------------+------------------+------------------+
| Protokol          | HTTP/2           | HTTP/1.1         |
| Format            | Protobuf (binary)| JSON (text)      |
| Suret             | Suretli          | Nisbeten yavas   |
| Streaming         | Var (4 nov)      | Yoxdur           |
| Kod generasiyasi  | Avtomatik        | Manuel           |
| Brauzer destəyi   | Mehdud           | Tam              |
| Debugging         | Cetindir         | Asandir          |
+-------------------+------------------+------------------+

Ne vaxt gRPC istifade etmeli:
- Microservice-ler arasi daxili elaqe
- Suret kritik olan yerler
- Streaming lazim olanda
- Polyglot (ferqli diller) microservice-ler

Ne vaxt REST istifade etmeli:
- Public API
- Brauzer client-leri
- Sadə CRUD emeliyyatlari
`)
}
