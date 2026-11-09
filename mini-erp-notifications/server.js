
require('dotenv').config()

const express = require('express')

const app = express()
app.use(express.json())

const PORT = Number(process.env.PORT || 3001)
const SERVICE_TOKEN = process.env.SERVICE_TOKEN

if (!SERVICE_TOKEN) {
  console.error('SERVICE_TOKEN is missing from .env')
  process.exit(1)
}

// One active low-stock alert per product.
// Map key: product ID
const activeAlerts = new Map()

const authenticateService = (req, res, next) => {
  const token = req.header('x-service-token')

  if (!token || token !== SERVICE_TOKEN) {
    return res.status(401).json({
      message: 'Unauthorized service request',
    })
  }

  next()
}

// GET /health
app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    service: 'mini-erp-notifications',
  })
})

// POST /notifications/low-stock
//
// Laravel sends the latest stock balance.
// Low stock -> create/update alert.
// Healthy stock -> remove active alert.
app.post(
  '/notifications/low-stock',
  authenticateService,
  (req, res) => {
    const {
      id,
      name,
      sku,
      stock_quantity,
      reorder_level,
    } = req.body

    const productId = Number(id)
    const stock = Number(stock_quantity)
    const reorderLevel = Number(reorder_level)

    if (
      !Number.isSafeInteger(productId) ||
      productId <= 0 ||
      typeof name !== 'string' ||
      !name.trim() ||
      typeof sku !== 'string' ||
      !sku.trim() ||
      !Number.isSafeInteger(stock) ||
      stock < 0 ||
      !Number.isSafeInteger(reorderLevel) ||
      reorderLevel < 0
    ) {
      return res.status(422).json({
        message: 'Invalid product data',
      })
    }

    if (stock <= reorderLevel) {
      const existingAlert = activeAlerts.get(productId)

      const alert = {
        id: productId,
        product_id: productId,
        product_name: name,
        sku,
        stock_quantity: stock,
        reorder_level: reorderLevel,
        message: `Low stock: ${name} has ${stock} units remaining.`,
        type: 'low_stock',
        status: 'active',
        created_at:
          existingAlert?.created_at ||
          new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }

      activeAlerts.set(productId, alert)

      console.log(
        `[LOW STOCK] ${name}: ${stock}/${reorderLevel}`
      )

      return res.json({
        message: 'Low-stock alert synchronized',
        status: 'active',
        data: alert,
      })
    }

    const wasActive = activeAlerts.delete(productId)

    if (wasActive) {
      console.log(
        `[RESOLVED] ${name}: stock is now ${stock}`
      )
    }

    return res.json({
      message: wasActive
        ? 'Low-stock alert resolved'
        : 'Product stock is healthy',
      status: 'resolved',
      data: null,
    })
  }
)

// GET /notifications
app.get(
  '/notifications',
  authenticateService,
  (req, res) => {
    const notifications = Array.from(
      activeAlerts.values()
    ).sort(
      (a, b) =>
        new Date(b.updated_at) -
        new Date(a.updated_at)
    )

    return res.json({
      data: notifications,
      total: notifications.length,
    })
  }
)

app.use((err, req, res, next) => {
  if (err instanceof SyntaxError && 'body' in err) {
    return res.status(400).json({
      message: 'Invalid JSON body',
    })
  }

  console.error(err)

  return res.status(500).json({
    message: 'Internal server error',
  })
})

app.listen(PORT, '127.0.0.1', () => {
  console.log(
    `Mini ERP notification service running at http://127.0.0.1:${PORT}`
  )
})
