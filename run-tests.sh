#!/bin/bash

# E2E测试快速运行脚本

set -e

echo "🚀 开始E2E测试..."
echo ""

# 检查Docker容器是否运行
echo "📦 检查Docker容器状态..."
if ! docker ps | grep -q card-code-unified; then
    echo "❌ Docker容器未运行"
    echo "正在启动容器..."
    docker-compose up -d
    echo "⏳ 等待服务启动..."
    sleep 5
fi

# 检查服务是否可访问
echo "🔍 检查服务可访问性..."
MAX_RETRIES=30
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if curl -s -o /dev/null -w "%{http_code}" http://localhost:3009/admin/login | grep -q "200"; then
        echo "✅ 服务已就绪"
        break
    fi
    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "⏳ 等待服务启动... ($RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "❌ 服务启动超时"
    exit 1
fi

# 检查pnpm依赖
if [ ! -d "node_modules" ]; then
    echo "📦 安装pnpm依赖..."
    pnpm install
fi

# 运行测试
echo ""
echo "🧪 运行E2E测试..."
echo "================================"
echo ""

pnpm test

# 显示测试结果
echo ""
echo "================================"
echo "✅ 测试完成！"
echo ""
echo "📊 查看详细报告："
echo "   pnpm run test:report"
echo ""
echo "📁 测试结果位置："
echo "   test-results/"
echo ""
