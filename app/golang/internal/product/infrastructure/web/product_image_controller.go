package web

import (
	"net/http"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/shared/domain"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"gorm.io/gorm"
)

// === ProductImage Entity (web layer-də inline saxlanılır — sadəlik üçün) ===

type ProductImage struct {
	ID        uuid.UUID `gorm:"type:char(36);primaryKey"`
	ProductID uuid.UUID `gorm:"type:char(36);not null;index"`
	FilePath  string    `gorm:"size:2048;not null"`
	FileSize  int64     `gorm:"not null;default:0"`
	MimeType  string    `gorm:"size:64"`
	IsPrimary bool      `gorm:"not null;default:false"`
	SortOrder int       `gorm:"not null;default:0"`
	CreatedAt time.Time
	UpdatedAt time.Time
}

func (ProductImage) TableName() string { return "product_images" }

// === Controller ===
//
// Laravel: ProductImageController.php  ·  Spring: ProductImageController.java
type ProductImageController struct {
	db *gorm.DB
}

func NewProductImageController(db *gorm.DB) *ProductImageController {
	return &ProductImageController{db: db}
}

// GET /api/products/:id/images
func (c *ProductImageController) Index(ctx *gin.Context) {
	productID, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	var images []ProductImage
	if err := c.db.Where("product_id = ?", productID).Order("sort_order ASC").Find(&images).Error; err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(images))
}

// POST /api/products/:id/images   form: file
func (c *ProductImageController) Store(ctx *gin.Context) {
	productID, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	file, err := ctx.FormFile("file")
	if err != nil {
		ctx.Error(domain.NewDomainError("file lazımdır"))
		return
	}

	id := uuid.New()
	img := ProductImage{
		ID:        id,
		ProductID: productID,
		FilePath:  "/storage/products/" + productID.String() + "/" + id.String() + ".jpg",
		FileSize:  file.Size,
		MimeType:  file.Header.Get("Content-Type"),
	}
	if err := c.db.Create(&img).Error; err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusCreated, api.SuccessWithMessage(img, "Şəkil yükləndi"))
}

// DELETE /api/products/:id/images/:imageId
func (c *ProductImageController) Destroy(ctx *gin.Context) {
	imageID, err := uuid.Parse(ctx.Param("imageId"))
	if err != nil {
		ctx.Error(err)
		return
	}
	if err := c.db.Delete(&ProductImage{}, "id = ?", imageID).Error; err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Silindi"))
}

// PATCH /api/products/:id/images/:imageId/primary
func (c *ProductImageController) SetPrimary(ctx *gin.Context) {
	productID, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	imageID, err := uuid.Parse(ctx.Param("imageId"))
	if err != nil {
		ctx.Error(err)
		return
	}

	if err := c.db.Model(&ProductImage{}).Where("product_id = ?", productID).
		Update("is_primary", false).Error; err != nil {
		ctx.Error(err)
		return
	}
	if err := c.db.Model(&ProductImage{}).Where("id = ?", imageID).
		Update("is_primary", true).Error; err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Primary olaraq təyin edildi"))
}

func (c *ProductImageController) RegisterRoutes(public, authed *gin.RouterGroup) {
	public.GET("/products/:id/images", c.Index)
	authed.POST("/products/:id/images", c.Store)
	authed.DELETE("/products/:id/images/:imageId", c.Destroy)
	authed.PATCH("/products/:id/images/:imageId/primary", c.SetPrimary)
}
