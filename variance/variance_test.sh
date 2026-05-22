#!/bin/bash
# variance_test.sh - Automated testing for Variance comparison system

# Configuration
TEST_ROOT="/tmp/variance_test"
AUTHOR_NAME="Test Author"
WORK_TITLE="Sample Work"
VERSIONS=("v1" "v2")
TEXT_CONTENT=("First version text with \italic\ and ^superscript^" 
              "Second version with \changes\ and ^references^")

# Clean previous test runs
cleanup() {
    rm -rf "${TEST_ROOT}"
    rm -f test_comparison.xml test_package.zip
    echo "Cleaned up test files"
}

# Create directory structure
setup_directories() {
    mkdir -p "${TEST_ROOT}/input"
    mkdir -p "${TEST_ROOT}/output"
    
    for version in "${VERSIONS[@]}"; do
        mkdir -p "${TEST_ROOT}/input/${AUTHOR_NAME// /_}/${WORK_TITLE// /_}/${version}"
    done
}

# Generate sample text files
create_text_files() {
    for i in "${!VERSIONS[@]}"; do
        echo "${TEXT_CONTENT[$i]}" > \
        "${TEST_ROOT}/input/${AUTHOR_NAME// /_}/${WORK_TITLE// /_}/${VERSIONS[$i]}/${VERSIONS[$i]}_lignes.txt"
    done
}

# Generate XML configuration
generate_xml() {
    cat <<EOF > test_comparison.xml
<root>
  <auteur>
    <prenom>${AUTHOR_NAME%% *}</prenom>
    <nom>${AUTHOR_NAME##* }</nom>
  </auteur>
  <oeuvre>
    <titre>${WORK_TITLE}</titre>
  </oeuvre>
  <arbre>
    $(for v in "${VERSIONS[@]}"; do echo "<version id=\"${v}\"/>"; done)
  </arbre>
  <informations vsource="${VERSIONS[0]}" vcible="${VERSIONS[1]}"/>
</root>
EOF
}

# Package test files
create_package() {
    (cd "${TEST_ROOT}/input" && zip -r "${TEST_ROOT}/test_package.zip" .)
    mv "${TEST_ROOT}/test_package.zip" .
}

# Run comparison process
run_comparison() {
    echo "=== PHP Version ==="
    php -v
    echo -e "\n=== PHP Modules ==="
    php -m
    echo -e "\n=== Running Comparison ==="
    php -d display_errors=On -d error_reporting=E_ALL \
        -f index.php \
        > "${TEST_ROOT}/output.log" 2>&1
    
    echo -e "\n=== Error Log ==="
    cat "${TEST_ROOT}/error.log"
}

# Main execution
main() {
    cleanup
    setup_directories
    create_text_files
    generate_xml
    create_package
    
    echo "Running comparison process..."
    run_comparison
    
    echo -e "\n=== Results ==="
    tree "${TEST_ROOT}/output"
    
    echo -e "\n=== Processed Files ==="
    find "${TEST_ROOT}/output" -name "*.xhtml" -exec grep -H '<em>\|</em>\|<sup>\|</sup>' {} \;
}

# Execute main function
main