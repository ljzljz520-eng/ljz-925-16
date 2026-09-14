#!/bin/bash

# 全面测试运行脚本
# 用法: ./run-all-tests.sh [type]
# type: all, unit, integration, e2e, coverage

set -e

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 打印带颜色的消息
print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_section() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

# 检查依赖
check_dependencies() {
    print_info "检查依赖..."

    # 检查PHP
    if ! command -v php &> /dev/null; then
        print_error "PHP未安装"
        exit 1
    fi

    # 检查Composer
    if ! command -v composer &> /dev/null; then
        print_error "Composer未安装"
        exit 1
    fi

    # 检查Node.js
    if ! command -v node &> /dev/null; then
        print_error "Node.js未安装"
        exit 1
    fi

    print_info "依赖检查通过 ✓"
}

# 安装依赖
install_dependencies() {
    print_section "安装依赖"

    # 安装PHP依赖
    if [ ! -d "backend/vendor" ]; then
        print_info "安装PHP依赖..."
        cd backend
        composer install --no-interaction
        cd ..
    else
        print_info "PHP依赖已安装 ✓"
    fi

    # 安装Node依赖
    if [ ! -d "node_modules" ]; then
        print_info "安装Node依赖..."
        pnpm install
    else
        print_info "Node依赖已安装 ✓"
    fi
}

# 运行PHP单元测试
run_unit_tests() {
    print_section "运行单元测试"
    cd backend
    if ./vendor/bin/phpunit --testsuite=Unit --colors=always; then
        print_info "单元测试通过 ✓"
    else
        print_error "单元测试失败 ✗"
        exit 1
    fi
    cd ..
}

# 运行PHP集成测试
run_integration_tests() {
    print_section "运行集成测试"
    cd backend
    if ./vendor/bin/phpunit --testsuite=Integration --colors=always; then
        print_info "集成测试通过 ✓"
    else
        print_error "集成测试失败 ✗"
        exit 1
    fi
    cd ..
}

# 运行所有PHP测试
run_php_tests() {
    print_section "运行所有PHP测试"
    cd backend
    if ./vendor/bin/phpunit --colors=always; then
        print_info "PHP测试通过 ✓"
    else
        print_error "PHP测试失败 ✗"
        exit 1
    fi
    cd ..
}

# 运行E2E测试
run_e2e_tests() {
    print_section "运行E2E测试"

    # 检查应用是否运行
    if ! curl -s http://localhost:3009 > /dev/null 2>&1; then
        print_warning "应用未运行，尝试启动..."
        docker-compose up -d
        print_info "等待应用启动..."
        sleep 10

        # 再次检查
        if ! curl -s http://localhost:3009 > /dev/null 2>&1; then
            print_error "应用启动失败"
            exit 1
        fi
    fi

    print_info "应用正在运行 ✓"

    if pnpm test; then
        print_info "E2E测试通过 ✓"
    else
        print_error "E2E测试失败 ✗"
        exit 1
    fi
}

# 生成测试覆盖率报告
generate_coverage() {
    print_section "生成测试覆盖率报告"
    cd backend
    if ./vendor/bin/phpunit --coverage-html coverage --colors=always; then
        print_info "覆盖率报告已生成 ✓"
        print_info "报告位置: backend/coverage/index.html"
    else
        print_error "生成覆盖率报告失败 ✗"
        exit 1
    fi
    cd ..
}

# 显示测试统计
show_statistics() {
    print_section "测试统计"

    # PHP测试统计
    if [ -f "backend/.phpunit.result.cache" ]; then
        print_info "PHP测试缓存已生成"
    fi

    # E2E测试统计
    if [ -d "test-results" ]; then
        local test_count=$(find test-results -name "*.xml" | wc -l)
        print_info "E2E测试结果: $test_count 个文件"
    fi

    # 覆盖率统计
    if [ -d "backend/coverage" ]; then
        print_info "覆盖率报告已生成"
        if command -v open &> /dev/null; then
            echo -e "\n运行以下命令查看覆盖率报告:"
            echo -e "  ${BLUE}open backend/coverage/index.html${NC}\n"
        fi
    fi
}

# 主函数
main() {
    local test_type=${1:-all}

    echo -e "${BLUE}"
    echo "╔════════════════════════════════════════╗"
    echo "║     卡密管理系统 - 测试套件           ║"
    echo "╚════════════════════════════════════════╝"
    echo -e "${NC}"

    check_dependencies
    install_dependencies

    case $test_type in
        unit)
            run_unit_tests
            ;;
        integration)
            run_integration_tests
            ;;
        php)
            run_php_tests
            ;;
        e2e)
            run_e2e_tests
            ;;
        coverage)
            run_php_tests
            generate_coverage
            ;;
        all)
            run_php_tests
            run_e2e_tests
            show_statistics
            ;;
        *)
            print_error "未知的测试类型: $test_type"
            echo ""
            echo "用法: $0 [type]"
            echo ""
            echo "可用的测试类型:"
            echo "  unit        - 只运行单元测试"
            echo "  integration - 只运行集成测试"
            echo "  php         - 运行所有PHP测试"
            echo "  e2e         - 只运行E2E测试"
            echo "  coverage    - 生成测试覆盖率报告"
            echo "  all         - 运行所有测试 (默认)"
            echo ""
            exit 1
            ;;
    esac

    echo -e "\n${GREEN}╔════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║         测试完成！✓                    ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════╝${NC}\n"
}

# 运行主函数
main "$@"
