package main

import (
	"encoding/json"
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"
)

type Task struct {
	ID        int       `json:"id"`
	Title     string    `json:"title"`
	Done      bool      `json:"done"`
	CreatedAt time.Time `json:"created_at"`
}

const storageFile = "tasks.json"

func load() []Task {
	data, err := os.ReadFile(storageFile)
	if err != nil {
		return nil
	}
	var tasks []Task
	json.Unmarshal(data, &tasks)
	return tasks
}

func save(tasks []Task) {
	data, _ := json.MarshalIndent(tasks, "", "  ")
	os.WriteFile(storageFile, data, 0644)
}

func cmdAdd(args []string) {
	if len(args) == 0 {
		fmt.Println("usage: task add <title>")
		return
	}
	tasks := load()
	id := 1
	if len(tasks) > 0 {
		id = tasks[len(tasks)-1].ID + 1
	}
	t := Task{ID: id, Title: strings.Join(args, " "), CreatedAt: time.Now()}
	tasks = append(tasks, t)
	save(tasks)
	fmt.Printf("Added #%d: %s\n", id, t.Title)
}

func cmdList() {
	tasks := load()
	if len(tasks) == 0 {
		fmt.Println("No tasks.")
		return
	}
	for _, t := range tasks {
		mark := "○"
		if t.Done {
			mark = "✓"
		}
		fmt.Printf("  %s [%d] %s\n", mark, t.ID, t.Title)
	}
}

func cmdDone(args []string) {
	if len(args) == 0 {
		fmt.Println("usage: task done <id>")
		return
	}
	id, err := strconv.Atoi(args[0])
	if err != nil {
		fmt.Println("invalid id")
		return
	}
	tasks := load()
	for i := range tasks {
		if tasks[i].ID == id {
			tasks[i].Done = true
			save(tasks)
			fmt.Printf("Done: #%d %s\n", id, tasks[i].Title)
			return
		}
	}
	fmt.Printf("task #%d not found\n", id)
}

func cmdDelete(args []string) {
	if len(args) == 0 {
		fmt.Println("usage: task delete <id>")
		return
	}
	id, err := strconv.Atoi(args[0])
	if err != nil {
		fmt.Println("invalid id")
		return
	}
	tasks := load()
	for i, t := range tasks {
		if t.ID == id {
			tasks = append(tasks[:i], tasks[i+1:]...)
			save(tasks)
			fmt.Printf("Deleted #%d\n", id)
			return
		}
	}
	fmt.Printf("task #%d not found\n", id)
}

func cmdClear() {
	tasks := load()
	var active []Task
	removed := 0
	for _, t := range tasks {
		if t.Done {
			removed++
		} else {
			active = append(active, t)
		}
	}
	save(active)
	fmt.Printf("Cleared %d completed task(s).\n", removed)
}

func usage() {
	fmt.Println(`Task Manager CLI

Commands:
  task add <title>    Add a new task
  task list           List all tasks
  task done <id>      Mark task as done
  task delete <id>    Delete a task
  task clear          Remove all completed tasks`)
}

func main() {
	if len(os.Args) < 2 {
		usage()
		return
	}
	cmd := os.Args[1]
	args := os.Args[2:]

	switch cmd {
	case "add":
		cmdAdd(args)
	case "list":
		cmdList()
	case "done":
		cmdDone(args)
	case "delete", "rm":
		cmdDelete(args)
	case "clear":
		cmdClear()
	default:
		usage()
	}
}
